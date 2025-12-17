import argparse
import csv
import logging
import sys
from datetime import datetime
from elasticsearch import Elasticsearch, helpers

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(message)s")

def prune_empty(obj):
    if isinstance(obj, dict):
        return {k: v for k, v in ((k, prune_empty(v)) for k, v in obj.items())
                if v not in (None, "", [], {})}
    if isinstance(obj, list):
        return [prune_empty(v) for v in obj if v not in (None, "", [], {})]
    return obj

def format_doc_contact(row, headers):
    doc = {h: row[i] for i, h in enumerate(headers)}
    domain = doc.get("domain", "").strip()
    first, last = doc.get("first_name", "").strip(), doc.get("last_name", "").strip()
    if not domain:
        return None, None
    doc_id = f"{domain}__{first}__{last}"

    doc["location"] = {"country": doc.pop("country", ""), "state": doc.pop("state", ""), "city": doc.pop("city", "")}

    # emails
    emails = []
    if doc.get("work_email"):
        emails.append({"email": doc["work_email"], "email_status": "active", "type": "work"})
    if doc.get("personal_email"):
        emails.append({"email": doc["personal_email"], "email_status": "active", "type": "personal"})
    doc.pop("work_email", None)
    doc.pop("personal_email", None)
    if emails:
        doc["emails"] = [e for e in emails if e["email"].strip()]

    # phones
    phones = []
    if doc.get("mobile_number"):
        phones.append({"phone_number": doc["mobile_number"], "type": "mobile", "is_valid": True})
    if doc.get("direct_number"):
        phones.append({"phone_number": doc["direct_number"], "type": "direct", "is_valid": True})
    doc.pop("mobile_number", None)
    doc.pop("direct_number", None)
    if phones:
        doc["phone_numbers"] = phones

    doc["website"] = domain
    company = domain.split(".")[0]
    if company:
        doc["company"] = company
    doc.pop("domain", None)

    doc["full_name"] = f"{first} {last}".strip()
    doc["linkedin_url"] = doc.pop("person_linkedin_url", "")

    doc = prune_empty(doc)
    return doc, doc_id

def try_parse_date(s):
    for fmt in ("%Y%m%dT%H:%M:%S%z", "%Y-%m-%dT%H:%M:%S%z", "%Y-%m-%dT%H:%M:%S", "%Y%m%d"):
        try:
            return datetime.strptime(s, fmt).isoformat()
        except Exception:
            continue
    return s or None


def format_doc_company(row, headers):
    doc = {h: row[i] for i, h in enumerate(headers)}
    domain, name = doc.get("domain", "").strip(), doc.get("name", "").strip()
    # Require LinkedIn for company
    linkedin = doc.get("linkedin_url", "").strip()
    if not linkedin:
        return None, None
    doc_id = f"{domain}__{name}__"

    doc["location"] = {
        "country": doc.pop("country", ""),
        "state": doc.pop("state", ""),
        "city": doc.pop("city", ""),
        "postal_code": doc.pop("postal_code", ""),
        "street": doc.pop("street", ""),
    }
    doc["annual_revenue"] = doc.pop("annual_revenue_usd", "")
    doc["business_category"] = doc.pop("industry", "")
    doc["business_description"] = doc.pop("short_description", "")
    doc["company"] = doc.pop("name", "")
    doc["company_address"] = doc.pop("address", "")
    doc["company_linkedin_url"] = linkedin
    doc.pop("linkedin_url", None)
    doc["company_phone"] = doc.pop("phone_number", "")
    doc["company_technologies"] = doc.pop("technologies", "")
    doc["employee_count"] = doc.pop("number_of_employees", "")

    funding = {
        "latest_funding": doc.pop("latest_funding", ""),
        "latest_funding_amount": doc.pop("latest_funding_usd", ""),
        "total_funding": doc.pop("total_funding_usd", ""),
    }
    last_raised = doc.pop("last_raised_at", "")
    if last_raised:
        parsed = try_parse_date(last_raised)
        if parsed:
            funding["last_raised_at"] = parsed
    doc["funding"] = prune_empty(funding)

    doc["social_media"] = {"facebook_url": doc.pop("facebook_url", ""), "twitter_url": doc.pop("twitter_url", "")}
    doc["website"] = doc.pop("domain", "")

    doc = prune_empty(doc)
    return doc, doc_id


def stream_csv(csv_file, index_name):
    with open(csv_file, newline="", encoding="utf-8") as f:
        reader = csv.reader(f)
        headers = next(reader)
        lower_headers = [h.lower() for h in headers]
        use_contact = "first_name" in lower_headers
        for row in reader:
            if use_contact:
                doc, doc_id = format_doc_contact(row, headers)
            else:
                doc, doc_id = format_doc_company(row, headers)
                # safeguard: company linkedin required
                if not doc or not doc.get("company_linkedin_url"):
                    continue
            yield {"_op_type": "index", "_index": index_name, "_id": doc_id, "_source": doc}


def main():
    parser = argparse.ArgumentParser()
    parser.add_argument("--index", required=True)
    parser.add_argument("--csv", required=True)
    parser.add_argument("--create-index", action="store_true")
    args = parser.parse_args()

    client = Elasticsearch(
        hosts=["https://139.59.12.129:9200"],
        api_key=("Bfh7B5AB4rtAHwvihYD3", "O8xgR9ydS8aN348hvRmDJg"),
        verify_certs=False,
        ssl_show_warn=False,
        headers={
            "Accept": "application/vnd.elasticsearch+json; compatible-with=8",
            "Content-Type": "application/vnd.elasticsearch+json; compatible-with=8",
        }
    )

    # ensure index exists
    if not client.indices.exists(index=args.index):
        if args.create_index:
            client.indices.create(index=args.index)
        else:
            logging.error("Index %s does not exist.", args.index)
            sys.exit(1)

    # bulk indexing
    try:
        success, errors = helpers.bulk(
            client.options(request_timeout=60),
            stream_csv(args.csv, args.index),
            chunk_size=5000,
            stats_only=False,
        )
        logging.info("Bulk indexing completed. Success=%d Errors=%s", success, errors)
    except Exception as e:
        logging.exception("Bulk indexing failed: %s", e)
        sys.exit(1)


if __name__ == "__main__":
    main()
