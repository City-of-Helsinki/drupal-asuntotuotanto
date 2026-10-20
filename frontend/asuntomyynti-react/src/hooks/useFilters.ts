import useSearchParams from './useSearchParams';
import { getParamsByName } from '../utils/getParamsByName';
import { useNavigate } from 'react-router-dom';
import { FilterConfigs, FilterName } from '../types/common';
import { useCallback } from 'react';

const useFilters = () => {
  const query = useSearchParams();
  const navigate = useNavigate();

  const getAllFilters = () => {
    return Array.from(query.entries()).filter(([, value]) => value !== '') as [FilterName, string][];
  };

  const getFilters = useCallback(
    (name: string): string[] => {
      return getParamsByName(query, name, []);
    },
    [query]
  );

  const getFilter = (name: string): string => {
    return query.get(name) || '';
  };

  const setFilter = (name: string, value: string) => {
    query.set(name, value);
    navigate(`?${query.toString()}`, { replace: true });
  };

  // Set array as value
  const setFilters = (name: string, values: string[]) => {
    setFilter(name, values.join(','));
  };

  // Add item to a list
  const addFilter = (name: string, value: string) => {
    const values = getParamsByName(query, name, []);
    values.push(value);
    setFilter(name, values.join(','));
  };

  // Remove item from a list
  const removeFilter = (name: string, value: string) => {
    const values = getParamsByName(query, name, []);
    const filteredValues = values.filter((x: string) => x !== value);

    if (filteredValues.length === 0) {
      query.delete(name);
      navigate(`?${query.toString()}`, { replace: true });
      return;
    }

    setFilter(name, filteredValues.join(','));
  };

  const clearFilter = (name: string) => {
    query.delete(name);
    navigate(`?${query.toString()}`, { replace: true });
  };

  const clearAllFilters = (filterConfig: FilterConfigs) => {
    Object.keys(filterConfig).forEach((name) => {
      query.delete(name);
    });
    navigate(`?${query.toString()}`, { replace: true });
  };

  const hasFilters = (filterConfig: FilterConfigs) => {
    return Object.keys(filterConfig).some((name) => {
      return !!query.get(name);
    });
  };

  return {
    hasFilters,
    clearAllFilters,
    getAllFilters,
    getFilter,
    getFilters,
    addFilter,
    setFilter,
    setFilters,
    removeFilter,
    clearFilter,
  };
};

export default useFilters;
