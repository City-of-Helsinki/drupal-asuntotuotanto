import { render, screen } from '@testing-library/react';
import { createRef } from 'react';
import SearchForm from './SearchForm';
import { BrowserRouter } from 'react-router-dom';
import { FilterName, DataConfig } from '../../../../types/common';
import { defaultConfig } from '../../utils/defaultConfig';
import styles from './SearchForm.module.scss';
import { enhanceFilterConfig } from '../../../../utils/enhanceFilterConfig';
import filterConfig from '../../mocks/filter-config.json';

const mockConfig: DataConfig = {
  filters: {
    [FilterName.Price]: defaultConfig(FilterName.Price),
    [FilterName.LivingArea]: defaultConfig(FilterName.LivingArea),
    [FilterName.ProjectBuildingType]: defaultConfig(FilterName.ProjectBuildingType),
    [FilterName.ProjectDistrictHaso]: defaultConfig(FilterName.ProjectDistrictHaso),
    [FilterName.ProjectDistrictHitas]: defaultConfig(FilterName.ProjectDistrictHitas),
    [FilterName.ProjectDistrict]: defaultConfig(FilterName.ProjectDistrict),
    [FilterName.Properties]: defaultConfig(FilterName.Properties),
    [FilterName.RoomCount]: defaultConfig(FilterName.RoomCount),
    [FilterName.StateOfSale]: defaultConfig(FilterName.StateOfSale),
  },
  static_content: {},
  apartment_application_status: {},
  token: 'test',
  user: { user_id: 0, email_address: null, username: '', applications: [] },
} as any;

const defaultProps = {
  config: mockConfig,
  pageTitle: '',
  projectOwnershipType: 'hitas',
  focusRef: createRef<HTMLDivElement>(),
  onSubmit: () => {},
  isLoading: false,
  isError: false,
};

test('renders SearchForm component', () => {
  const { container } = render(
    <BrowserRouter>
      <SearchForm {...defaultProps} config={mockConfig} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders form submit button', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} />
    </BrowserRouter>
  );
  expect(screen.queryAllByText('SEARCH:search')).not.toEqual([]);
});

test('renders given pagetitle', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} pageTitle={'testTitle'} />
    </BrowserRouter>
  );
  expect(screen.queryByText('testTitle')).not.toBeNull();
});

test('renders error message', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} isError />
    </BrowserRouter>
  );
  expect(screen.queryByText('SEARCH:filters-error-text')).not.toBeNull();
});

test('renders without an error', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} isError={false} />
    </BrowserRouter>
  );
  expect(screen.queryByText('SEARCH:filters-error-text')).toBeNull();
});

test('loading animation is shown when isLoading is true', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} isLoading />
    </BrowserRouter>
  );

  const elem = screen.getByLabelText('SEARCH:aria-filters-title');

  expect(elem.classList.contains(styles.isLoading)).toBe(true);
});

test('loading animation is hidden when isLoading is false', () => {
  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} isLoading={false} />
    </BrowserRouter>
  );

  const elem = screen.getByLabelText('SEARCH:aria-filters-title');

  expect(elem.classList.contains(styles.isLoading)).toBe(false);
});

test('renders Additional selections and floor as separate dropdowns and hides excluded options', () => {
  const enhancedConfig = {
    ...mockConfig,
    filters: enhanceFilterConfig(filterConfig.filters),
  } as DataConfig;

  render(
    <BrowserRouter>
      <SearchForm {...defaultProps} config={enhancedConfig} />
    </BrowserRouter>
  );

  expect(screen.getByText('Additional selections')).toBeDefined();
  expect(screen.getByText('SEARCH:floor')).toBeDefined();

  expect(screen.getByText('ES:has_balcony')).toBeDefined();
  expect(screen.queryAllByText('ES:TOP_FLOOR')).toHaveLength(1);
  expect(screen.queryAllByText('ES:BOTTOM_FLOOR')).toHaveLength(1);
  expect(screen.queryAllByText('ES:NOT_TOP_FLOOR')).toHaveLength(1);
  expect(screen.queryAllByText('ES:NOT_BOTTOM_FLOOR')).toHaveLength(1);

  expect(screen.queryByText('ES:SINGLE_LEVEL')).toBeNull();
  expect(screen.queryByText('ES:HAS_FRENCH_BALCONY')).toBeNull();
  expect(screen.queryByText('ES:project_has_elevator')).toBeNull();
});

