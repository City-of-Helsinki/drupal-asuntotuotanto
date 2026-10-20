import { fireEvent, render, screen } from '@testing-library/react';
import TagList from './TagList';
import { BrowserRouter } from 'react-router-dom';
import { FilterName } from '../../../../../types/common';
import { defaultConfig } from '../../../utils/defaultConfig';
import { enhanceFilterConfig } from '../../../../../utils/enhanceFilterConfig';
import { vi } from 'vitest';

const removeFilter = vi.fn();
const clearFilter = vi.fn();
let currentFilters: [FilterName, string][] = [];

vi.mock('../../../../../hooks/useFilters', () => ({
  default: () => ({
    clearFilter,
    removeFilter,
    getAllFilters: () => currentFilters,
  }),
}));

const mockFilters = {
  [FilterName.Price]: defaultConfig(FilterName.Price),
  [FilterName.LivingArea]: defaultConfig(FilterName.LivingArea),
  [FilterName.ProjectBuildingType]: defaultConfig(FilterName.ProjectBuildingType),
  [FilterName.ProjectDistrictHaso]: defaultConfig(FilterName.ProjectDistrictHaso),
  [FilterName.ProjectDistrictHitas]: defaultConfig(FilterName.ProjectDistrictHitas),
  [FilterName.ProjectDistrict]: defaultConfig(FilterName.ProjectDistrict),
  [FilterName.Properties]: {
    ...defaultConfig(FilterName.Properties),
    items: ['TOP_FLOOR', 'HAS_BALCONY'],
  },
  [FilterName.RoomCount]: defaultConfig(FilterName.RoomCount),
  [FilterName.StateOfSale]: defaultConfig(FilterName.StateOfSale),
} as any;

beforeEach(() => {
  currentFilters = [];
  removeFilter.mockReset();
  clearFilter.mockReset();
});

test('renders TagList component', () => {
  const enhancedFilters = enhanceFilterConfig(mockFilters);

  const { container } = render(
    <BrowserRouter>
      <TagList filters={enhancedFilters} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('shows property tags and removes one value on click', () => {
  const enhancedFilters = enhanceFilterConfig(mockFilters);
  currentFilters = [[FilterName.Properties, 'TOP_FLOOR,HAS_BALCONY']];

  render(
    <BrowserRouter>
      <TagList filters={enhancedFilters} />
    </BrowserRouter>
  );

  expect(screen.getByText('ES:TOP_FLOOR')).toBeDefined();
  expect(screen.getByText('ES:HAS_BALCONY')).toBeDefined();

  fireEvent.click(screen.getByLabelText('SEARCH:remove-tag ES:TOP_FLOOR'));
  expect(removeFilter).toHaveBeenCalledWith('properties', 'TOP_FLOOR');
});

test('clears non-multiselect tag on click', () => {
  const enhancedFilters = enhanceFilterConfig(mockFilters);
  currentFilters = [[FilterName.Price, '5']];

  render(
    <BrowserRouter>
      <TagList filters={enhancedFilters} />
    </BrowserRouter>
  );

  fireEvent.click(screen.getByRole('button', { name: /SEARCH:remove-tag/ }));
  expect(clearFilter).toHaveBeenCalledWith('price');
});
