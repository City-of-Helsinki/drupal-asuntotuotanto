import { ElasticsearchQueryBody, FilterName, QueryParams, StateOfSale } from '../types/common';
import { useCallback, useContext, useEffect, useState } from 'react';
import { DataContext } from '../modules/search/DataContext';
import useFilters from './useFilters';

const buildBaseQuery = (ownershipType: string, upcomingOnly: string): ElasticsearchQueryBody => {
  const ownership = { project_ownership_type: ownershipType };
  if (upcomingOnly.toLowerCase() === 'true') {
    return { ...ownership, project_state_of_sale: [StateOfSale.Upcoming] };
  }
  return ownership;
};

const useSearchQuery = (projectOwnershipType: string, showUpcomingOnly: string) => {
  // Include ownership (and optional upcoming filter) immediately so elasticsearch is never
  // called with `{}` on the first render after config loads (before useEffect runs).
  const [query, setQuery] = useState<ElasticsearchQueryBody>(() =>
    buildBaseQuery(projectOwnershipType, showUpcomingOnly)
  );
  const { data: config } = useContext(DataContext) || {};
  const { getFilters } = useFilters();

  const buildQuery = useCallback(() => {
    if (!config) return {};

    const { filters } = config;
    if (!filters) return {};

    const queryFilters = (Object.keys(filters) as FilterName[]).reduce((allFilters: QueryParams, name) => {
      const values = getFilters(name);
      const { getQuery } = filters[name] || {};

      if (values.length === 0 || !getQuery) {
        return allFilters;
      }

      allFilters[name] = values;

      return { ...allFilters, ...getQuery(values) };
    }, {});

    // Frontend-only floor range filter: serialize URL value "floor=min,max" to backend keys.
    const [floorMinRaw = '', floorMaxRaw = ''] = getFilters('floor');

    if (floorMinRaw || floorMaxRaw) {
      queryFilters.floor = [floorMinRaw, floorMaxRaw];

      if (floorMinRaw !== '') {
        const floorMin = Number(floorMinRaw);
        if (!Number.isNaN(floorMin)) {
          queryFilters.floor_min = floorMin;
        }
      }

      if (floorMaxRaw !== '') {
        const floorMax = Number(floorMaxRaw);
        if (!Number.isNaN(floorMax)) {
          queryFilters.floor_max = floorMax;
        }
      }
    }

    return queryFilters;
  }, [config, getFilters]);

  const updateQuery = useCallback(() => {
    const localQuery = buildQuery();

    // Always include project ownership type in the query
    const combinedQuery = { ...localQuery, ...buildBaseQuery(projectOwnershipType, showUpcomingOnly) };

    setQuery(combinedQuery);
  }, [buildQuery, projectOwnershipType, showUpcomingOnly]);

  useEffect(() => {
    // Update query ONCE when the config is loaded and when manually triggered
    updateQuery();
    // eslint-disable-next-line
  }, [config]);

  return { query, updateQuery };
};

export default useSearchQuery;
