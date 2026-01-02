# Frontend App - lacleo.ai

## Overview

The `app` service is the React-based frontend application for lacleo.ai, providing the user interface for B2B contact and company search.

## Tech Stack

- **Framework**: React 18
- **State Management**: Redux Toolkit
- **Routing**: React Router v6
- **HTTP Client**: Axios
- **UI Components**: Radix UI
- **Styling**: Tailwind CSS
- **Build Tool**: Vite

## Key Features

### 1. Search Interface
- Unified search for companies and contacts
- Advanced filter panel with 23+ filters
- Real-time search suggestions
- Saved filter management

### 2. AI Search
- Natural language query input
- Automatic filter generation
- Entity detection

### 3. Data Management
- Contact/company reveal
- CSV export
- Bulk operations

### 4. Account & Billing
- Credit usage tracking
- Purchase credits
- Subscription management

## Project Structure

```
app/
├── src/
│   ├── app/
│   │   ├── redux/
│   │   │   └── store.ts
│   │   └── hooks/
│   ├── components/
│   │   └── ui/              # Reusable UI components
│   ├── features/
│   │   ├── filters/         # Filter system
│   │   ├── searchExecution/ # Search logic
│   │   ├── aisearch/        # AI search
│   │   └── billing/         # Billing UI
│   ├── services/
│   │   └── api/
│   │       └── apiSlice.ts  # RTK Query API
│   └── main.tsx
├── public/
└── index.html
```

## Environment Variables

Create a `.env` file:

```env
VITE_API_URL=https://local-api.lacleo.test
VITE_ACCOUNTS_URL=https://local-accounts.lacleo.test
VITE_APP_NAME=lacleo.ai
```

## Installation

```bash
# Install dependencies
npm install

# Start development server
npm run dev

# Build for production
npm run build

# Preview production build
npm run preview
```

## Development

```bash
# Start dev server (with HMR)
npm run dev

# Type checking
npm run type-check

# Linting
npm run lint

# Format code
npm run format
```

## State Management

### Redux Store Structure

```typescript
{
  filters: {
    activeFilters: {},
    availableFilters: [],
    filterValues: {}
  },
  searchExecution: {
    results: [],
    loading: false,
    error: null
  },
  billing: {
    credits: 0,
    usage: []
  }
}
```

### Key Slices

- **filterSlice**: Manages filter state and DSL generation
- **searchExecutionSlice**: Handles search queries and results
- **billingSlice**: Tracks credits and usage

## API Integration

All API calls use RTK Query via `apiSlice.ts`:

```typescript
// Example: Search endpoint
const { data, isLoading } = useSearchQuery({
  type: 'company',
  filters: activeFilters,
  page: 1
});
```

### Authentication

- Uses Sanctum for session-based auth
- CSRF token automatically included
- Credentials sent with every request

## Filter System

### Filter Types

1. **Text Filters**: company_name, job_title, etc.
2. **Multi-Select**: technologies, seniority, departments
3. **Range**: employee_count, annual_revenue, total_funding
4. **Boolean**: has_funding, email_exists

### Filter DSL Generation

Filters are converted to Elasticsearch DSL in `filterSlice.ts`:

```typescript
{
  company: {
    employee_count: {
      range: { min: 10, max: 1000 }
    }
  }
}
```

## Components

### Key Components

- **UnifiedFilters**: Main filter panel
- **SearchTable**: Results table with pagination
- **ActiveFilterChips**: Display active filters
- **ExportModal**: CSV export interface
- **CreditUsageModal**: Billing information

### UI Components (Radix UI)

- Dialog, Dropdown, Select, Slider
- Tooltip, Popover, Checkbox
- All styled with Tailwind CSS

## Routing

```
/                    - Dashboard/Search
/ai-search           - AI-powered search
/saved-filters       - Manage saved filters
/billing             - Credits and subscriptions
/settings            - Account settings
```

## Performance Optimization

- Code splitting with React.lazy()
- Memoization with useMemo/useCallback
- Virtual scrolling for large lists
- Debounced search inputs
- Redux state normalization

## Testing

```bash
# Run tests
npm run test

# Run tests with coverage
npm run test:coverage

# Run E2E tests
npm run test:e2e
```

## Build & Deployment

```bash
# Production build
npm run build

# Analyze bundle size
npm run build -- --analyze

# Preview production build
npm run preview
```

## Troubleshooting

### CORS Issues
- Ensure API service has correct CORS configuration
- Check `VITE_API_URL` matches API service URL
- Verify credentials are being sent

### Authentication Issues
- Clear browser cookies
- Check Sanctum configuration
- Verify CSRF token is being sent

### Filter Issues
- Clear Redux state
- Check filter DSL generation
- Verify API filter configuration

## Contributing

1. Follow React best practices
2. Use TypeScript for type safety
3. Write tests for new features
4. Follow Tailwind CSS conventions
5. Run linter before committing

## License

Proprietary - All rights reserved
