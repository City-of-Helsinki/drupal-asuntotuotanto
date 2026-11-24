import axios from 'axios';
import { useQuery } from '@tanstack/react-query';
import filterApartmentA0 from '../modules/search/utils/filterApartmentA0';
import mapSearchResults from '../modules/search/utils/mapSearchResults';
import { ElasticsearchQueryBody } from '../types/common';

const searchPath = import.meta.env.VITE_ELASTICSEARCH_PATH || 'elasticsearch';

const hasProjectOwnershipType = (body: ElasticsearchQueryBody): boolean => {
  const value = body.project_ownership_type;
  return typeof value === 'string' && value.trim().length > 0;
};

const useSearchResults = (
  query: ElasticsearchQueryBody,
  queryHeaders: { token?: string },
  currentLang: string,
  keepA0 = false
) => {
  const fetchProjects = async () => {
    // Wait for token before trying to fetch data
    if (!queryHeaders.token) {
      return [];
    }

    if (!hasProjectOwnershipType(query)) {
      return [];
    }

    const queryAsJSON = JSON.stringify(query);

    const { data } = await axios.post(`/${currentLang}/${searchPath}`, queryAsJSON, {
      headers: {
        'X-CSRF-TOKEN': queryHeaders.token,
        'Content-Type': 'application/json',
      },
    });

    const dataAsArray = (Array.isArray(data) ? data : Object.values(data)) as Array<
      Array<Record<string, unknown>>
    >;
    const mappedSearchResults = dataAsArray?.map(mapSearchResults) || [];
    if (keepA0) {
      return mappedSearchResults;
    } else {
      return filterApartmentA0(mappedSearchResults);
    }
  };

  const canFetch = Boolean(queryHeaders.token) && hasProjectOwnershipType(query);

  return useQuery({
    queryKey: ['searchResults', query, queryHeaders],
    queryFn: fetchProjects,
    enabled: canFetch,
    placeholderData: [],
    refetchOnWindowFocus: false,
  });
};

export default useSearchResults;
