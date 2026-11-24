import { render } from '@testing-library/react';
import SearchContainer from './SearchContainer';
import axios from 'axios';
import searchResponse from './mocks/search-response.json';
import { BrowserRouter } from 'react-router-dom';
import { vi } from 'vitest';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';

vi.mock('axios', () => ({
  default: {
    post: vi.fn(),
  },
}));

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: {
      changeLanguage: vi.fn(),
    },
  }),
}));

const mockedAxios = axios as unknown as { post: ReturnType<typeof vi.fn> };

test('renders Search component', () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
  mockedAxios.post.mockResolvedValue({ data: searchResponse });

  const { container } = render(
    <QueryClientProvider client={queryClient}>
      <BrowserRouter>
        <SearchContainer />
      </BrowserRouter>
    </QueryClientProvider>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});
