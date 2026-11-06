import { act, renderHook } from '@testing-library/react';
import { createElement, PropsWithChildren } from 'react';
import { MemoryRouter } from 'react-router-dom';
import { DataContext } from '../modules/search/DataContext';
import useSearchQuery from './useSearchQuery';
import { enhanceFilterConfig } from '../utils/enhanceFilterConfig';
import filterConfig from '../modules/search/mocks/filter-config.json';

describe('useSearchQuery', () => {
  const wrapper = ({ children }: PropsWithChildren) =>
    createElement(
      MemoryRouter,
      { initialEntries: ['/?properties=TOP_FLOOR,HAS_BALCONY&floor=2,6'] },
      createElement(
        DataContext.Provider,
        {
          value: {
            data: {
              filters: enhanceFilterConfig(filterConfig.filters),
            },
          } as any,
        },
        children
      )
    );

  it('includes selected properties in the backend query body', () => {
    const { result } = renderHook(() => useSearchQuery('hitas', 'false'), { wrapper });

    act(() => {
      result.current.updateQuery();
    });

    expect(result.current.query.project_ownership_type).toBe('hitas');
    expect(result.current.query.properties).toEqual(['TOP_FLOOR', 'HAS_BALCONY']);
    expect(result.current.query.floor).toEqual(['2', '6']);
    expect(result.current.query.floor_min).toBe(2);
    expect(result.current.query.floor_max).toBe(6);
  });
});
