import axios from 'axios';
import useSearchResults from './useSearchResults';
import { renderHook, waitFor } from '@testing-library/react';
import searchResponse from '../modules/search/mocks/search-response.json';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import { ReactNode } from 'react';
import { describe, expect, it, vi } from 'vitest';
import { ElasticsearchQueryBody } from '../types/common';

vi.mock('axios', () => ({
  default: {
    post: vi.fn(),
  },
}));
const mockedAxios = axios as unknown as { post: ReturnType<typeof vi.fn> };

const malformedResponse = { data: {} };

const baseSearchBody: ElasticsearchQueryBody = { project_ownership_type: 'hitas' };

const emptyToken = { token: undefined };
const dummyToken = { token: 'xyz' };

const lang = 'fi';

const createWrapper = () => {
  const queryClient = new QueryClient({
    defaultOptions: {
      queries: {
        retry: false,
      },
    },
  });
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={queryClient}>{children}</QueryClientProvider>
  );
};

describe('useSearchResult', () => {
  it('should return empty array on first render', async () => {
    const { result } = renderHook(() => useSearchResults(baseSearchBody, emptyToken, lang), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.data).toEqual([]));
  });

  it('should return empty array on malformed response', async () => {
    mockedAxios.post.mockResolvedValue(malformedResponse);

    const { result } = renderHook(() => useSearchResults(baseSearchBody, dummyToken, lang), {
      wrapper: createWrapper(),
    });

    await waitFor(() => expect(result.current.data).toEqual([]));
  });

  it('should return array of three projects', async () => {
    mockedAxios.post.mockResolvedValue({ data: searchResponse });

    const { result } = renderHook(() => useSearchResults(baseSearchBody, dummyToken, lang), {
      wrapper: createWrapper(),
    });

    await waitFor(() => {
      expect(result.current.error).toBeNull();
      expect(result.current.data).toHaveLength(3);
    });
  });

  it('should not POST when project_ownership_type is missing or blank', async () => {
    mockedAxios.post.mockClear();

    renderHook(
      () =>
        useSearchResults({} as ElasticsearchQueryBody, dummyToken, lang),
      { wrapper: createWrapper() }
    );

    await waitFor(() => expect(mockedAxios.post).not.toHaveBeenCalled());
  });
});
