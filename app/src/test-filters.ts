import { buildSearchQuery } from './app/utils/buildSearchQuery';

// Test annual revenue filter
const testFilters1 = {
  annual_revenue: [{ id: '0-1M', name: '$0 - $1M', type: 'include' as const, value: '' }],
};

const result1 = buildSearchQuery(testFilters1);
console.log('Annual Revenue Filter Test:');
console.log(JSON.stringify(result1, null, 2));

// Test founded year filter
const testFilters2 = {
  founded_year: [{ id: '1976-1990', name: '1976 - 1990', type: 'include' as const, value: '' }],
};

const result2 = buildSearchQuery(testFilters2);
console.log('\nFounded Year Filter Test:');
console.log(JSON.stringify(result2, null, 2));

// Test total funding filter
const testFilters3 = {
  total_funding: [{ id: '1M-10M', name: '$1M - $10M', type: 'include' as const, value: '' }],
};

const result3 = buildSearchQuery(testFilters3);
console.log('\nTotal Funding Filter Test:');
console.log(JSON.stringify(result3, null, 2));
