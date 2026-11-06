import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import { vi } from 'vitest';

import ProjectApartmentsWidget from './ProjectApartmentsWidget';
import { DataContext } from '../DataContext';
import { enhanceFilterConfig } from '../../../utils/enhanceFilterConfig';
import filterConfig from '../mocks/filter-config.json';

import useSearchResults from '../../../hooks/useSearchResults';

vi.mock('react-i18next', () => ({
  useTranslation: () => ({
    t: (key: string) => key,
    i18n: { changeLanguage: vi.fn() },
  }),
}));

vi.mock('../../../hooks/useSearchResults', () => ({
  default: vi.fn(),
}));

const mockedUseSearchResults = vi.mocked(useSearchResults);

const configWithFilters = {
  ...filterConfig,
  filters: enhanceFilterConfig(filterConfig.filters),
};

test('renders filter fields even with no search results', () => {
  mockedUseSearchResults.mockReturnValue({
    data: [],
    isFetching: false,
    isError: false,
  } as any);

  render(
    <MemoryRouter>
      <DataContext.Provider
        value={{
          data: configWithFilters,
          isLoading: false,
          isError: false,
        } as any}
      >
        <ProjectApartmentsWidget
          projectUuid="test-project-uuid"
          projectOwnershipType="hitas"
          projectStateOfSale="READY"
        />
      </DataContext.Provider>
    </MemoryRouter>
  );

  expect(screen.getByText('Room count')).toBeDefined();
  expect(screen.getByText('area, m2')).toBeDefined();
});