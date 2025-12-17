<?php

namespace App\Utilities;

class SearchTermParser
{
    protected array $tokens = [];

    protected int $position = 0;

    public function parse(string $query): array
    {
        if (empty($query)) {
            return [];
        }

        $this->tokenize($query);

        return $this->parseExpression();
    }

    protected function tokenize(string $query): void
    {
        $this->tokens = [];
        $this->position = 0;

        $pattern = '/\s*("(?:[^"\\\\]|\\\\.)*"|\bAND\b|\bOR\b|\(|\)|\S+)\s*/';
        preg_match_all($pattern, $query, $matches);

        foreach ($matches[1] as $token) {
            if (trim($token) !== '') {
                $this->tokens[] = $token;
            }
        }
    }

    protected function parseExpression(): array
    {
        $terms = [$this->parseTerm()];

        while ($this->position < count($this->tokens)) {
            $token = $this->tokens[$this->position];

            if ($token === ')') {
                break;
            }

            if ($token === 'OR') {
                $this->position++;
                $terms[] = $this->parseTerm();
            } elseif ($token === 'AND') {
                $this->position++;
                $lastTerm = array_pop($terms);
                $terms[] = [
                    'type' => 'and',
                    'terms' => [$lastTerm, $this->parseTerm()],
                ];
            } else {
                // Implicit AND
                $lastTerm = array_pop($terms);
                $terms[] = [
                    'type' => 'and',
                    'terms' => [$lastTerm, $this->parseTerm()],
                ];
            }
        }

        if (count($terms) > 1) {
            return [
                'type' => 'or',
                'terms' => $terms,
            ];
        }

        return $terms[0];
    }

    protected function parseTerm(): array
    {
        if ($this->position >= count($this->tokens)) {
            throw new \InvalidArgumentException('Unexpected end of query');
        }

        $token = $this->tokens[$this->position++];

        if ($token === '(') {
            $expression = $this->parseExpression();
            if ($this->position >= count($this->tokens) || $this->tokens[$this->position] !== ')') {
                throw new \InvalidArgumentException('Missing closing parenthesis');
            }
            $this->position++;

            return $expression;
        }

        if (str_starts_with($token, '"') && str_ends_with($token, '"')) {
            return [
                'type' => 'phrase',
                'value' => substr($token, 1, -1),
            ];
        }

        return [
            'type' => 'term',
            'value' => $token,
        ];
    }
}
