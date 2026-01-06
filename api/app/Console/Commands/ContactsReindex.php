<?php

namespace App\Console\Commands;

use App\Models\Contact;
use Illuminate\Console\Command;

class ContactsReindex extends Command
{
    protected $signature = 'contacts:reindex {--refresh : Refresh index mapping before reindex}';

    protected $description = 'Recompute and populate seniority_level and department fields for contacts in Elasticsearch';

    public function handle(): int
    {
        $model = Contact::class;
        $elastic = $model::elastic();

        if ($this->option('refresh')) {
            $this->info('Refreshing Contact index...');
            $model::refreshIndex();
        }

        $this->info('Starting contacts reindex...');

        $page = 1;
        $perPage = 1000;
        while (true) {
            $result = $elastic->paginate($page, $perPage);
            $hits = $result['hits']['hits'] ?? [];
            if (empty($hits)) {
                break;
            }

            foreach ($hits as $hit) {
                $id = $hit['_id'] ?? null;
                $src = $hit['_source'] ?? [];
                if (!$id) {
                    continue;
                }

                $title = (string) ($src['title'] ?? '');
                $t = mb_strtolower($title);

                $seniority = null;
                if ($t !== '') {
                    if (preg_match('/\b(ceo|cto|cfo|coo|cso|ciso|president|founder|co-founder|chief)\b/i', $title)) {
                        $seniority = 'Executive';
                    } elseif (preg_match('/\b(vp|vice\s+president|svp|avp)\b/i', $title)) {
                        $seniority = 'VP';
                    } elseif (preg_match('/\bdirector\b/i', $t)) {
                        $seniority = 'Director';
                    } elseif (preg_match('/\bmanager\b/i', $t)) {
                        $seniority = 'Manager';
                    } elseif (preg_match('/\b(lead|head)\b/i', $t)) {
                        $seniority = 'Lead';
                    } elseif (preg_match('/\b(intern|junior|associate)\b/i', $t)) {
                        $seniority = 'Entry';
                    } elseif (preg_match('/\b(senior|sr\.?|staff)\b/i', $t)) {
                        $seniority = 'Senior';
                    } elseif (preg_match('/\b(mid|middle)\b/i', $t)) {
                        $seniority = 'Mid';
                    }
                }

                $department = null;
                if ($t !== '') {
                    if (preg_match('/\b(marketing|growth|brand|performance)\b/i', $t)) {
                        $department = 'Marketing';
                    } elseif (preg_match('/\b(sales|revenue|account\s*exec|business\s*development)\b/i', $t)) {
                        $department = 'Sales';
                    } elseif (preg_match('/\b(product|pm|program\s*manager)\b/i', $t)) {
                        $department = 'Product';
                    } elseif (preg_match('/\b(engineering|tech|software|developer|devops|data\s*engineer)\b/i', $t)) {
                        $department = 'Engineering';
                    } elseif (preg_match('/\b(operations|ops|supply\s*chain|logistics)\b/i', $t)) {
                        $department = 'Operations';
                    } elseif (preg_match('/\b(finance|accounting)\b/i', $t)) {
                        $department = 'Finance';
                    } elseif (preg_match('/\b(hr|talent|people)\b/i', $t)) {
                        $department = 'HR';
                    } elseif (preg_match('/\b(it|information\s*technology|systems|cio|sysadmin)\b/i', $t)) {
                        $department = 'IT';
                    }
                }

                $elastic->updateDocument($id, [
                    'seniority_level' => $seniority,
                    'department' => $department,
                ]);
            }

            $this->info('Processed page ' . $page . ' (' . count($hits) . ' docs)');
            $page++;
        }

        $this->info('Contacts reindex completed');

        return 0;
    }
}