import elasticsearch
from elasticsearch import helpers
import json
import os
import time
import argparse
from concurrent.futures import ThreadPoolExecutor

# Configuration - Adjust these or pass as args
ES_HOST = os.getenv('ELASTICSEARCH_HOST', 'http://localhost:9200')
ES_USER = os.getenv('ELASTICSEARCH_USERNAME', 'elastic')
ES_PASS = os.getenv('ELASTICSEARCH_PASSWORD', 'changeme')
CHUNK_SIZE = 2000 # Number of docs per bulk request
MAX_WORKERS = 4   # Number of parallel threads

def connect_es():
    """Connect to Elasticsearch"""
    print(f"Connecting to Elasticsearch at {ES_HOST}...")
    try:
        es = elasticsearch.Elasticsearch(
            [ES_HOST],
            basic_auth=(ES_USER, ES_PASS) if ES_USER else None,
            verify_certs=False, # Set to True for production with valid certs
            request_timeout=60
        )
        if not es.ping():
            raise Exception("Could not ping Elasticsearch")
        print("Connected successfully.")
        return es
    except Exception as e:
        print(f"Connection failed: {e}")
        exit(1)

def generate_actions(file_path, index_name):
    """Generator to yield documents from a JSONL file"""
    with open(file_path, 'r', encoding='utf-8') as f:
        for i, line in enumerate(f):
            if not line.strip():
                continue
            try:
                doc = json.loads(line)
                # Ensure _index is set, or let helper handle it via index= param
                # We can also handle _id if present in doc
                action = {
                    "_index": index_name,
                    "_source": doc
                }
                if 'id' in doc:
                    action['_id'] = doc['id']
                elif '_id' in doc:
                    action['_id'] = doc['_id']
                    del doc['_id']
                yield action
            except json.JSONDecodeError as e:
                print(f"Skipping bad JSON at line {i}: {e}")

def bulk_import(es, file_path, index_name):
    """Run the bulk import"""
    start_time = time.time()
    print(f"Starting import from {file_path} to index {index_name}...")
    
    # Disable refresh for speed? (Optional: Do this manually via curl)
    # es.indices.put_settings(index=index_name, body={"index": {"refresh_interval": "-1"}})

    try:
        success_count = 0
        error_count = 0
        
        # parallel_bulk is faster for 2TB
        for success, info in helpers.parallel_bulk(
            es, 
            generate_actions(file_path, index_name), 
            thread_count=MAX_WORKERS,
            chunk_size=CHUNK_SIZE,
            queue_size=4,
            raise_on_error=False
        ):
            if success:
                success_count += 1
            else:
                error_count += 1
                if error_count % 1000 == 0:
                    print(f"Encountered {error_count} errors so far...")
            
            if (success_count + error_count) % 10000 == 0:
                elapsed = time.time() - start_time
                rate = (success_count + error_count) / elapsed
                print(f"Processed {success_count + error_count} docs. Rate: {rate:.0f} docs/s")
                
    except Exception as e:
        print(f"Import failed: {e}")
    finally:
        # Restore refresh?
        # es.indices.put_settings(index=index_name, body={"index": {"refresh_interval": "1s"}})
        pass

    end_time = time.time()
    print(f"Import Complete!")
    print(f"Total Docs: {success_count}")
    print(f"Failed Docs: {error_count}")
    print(f"Time Taken: {end_time - start_time:.2f}s")

if __name__ == "__main__":
    parser = argparse.ArgumentParser(description="Bulk Import JSONL to Elasticsearch")
    parser.add_argument('file', help="Path to JSONL file")
    parser.add_argument('index', help="Target Elasticsearch Index (not Alias)")
    parser.add_argument('--host', default=ES_HOST, help="Elasticsearch Host")
    
    args = parser.parse_args()
    ES_HOST = args.host
    
    if not os.path.exists(args.file):
        print(f"File not found: {args.file}")
        exit(1)

    es_client = connect_es()
    bulk_import(es_client, args.file, args.index)
