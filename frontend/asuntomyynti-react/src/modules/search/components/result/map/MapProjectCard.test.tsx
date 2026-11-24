import { render, screen } from '@testing-library/react';
import MapProjectCard from './MapProjectCard';

import mockProject from '../../../mocks/single-project.json';
import { FilterName } from '../../../../../types/common';
import { defaultConfig } from '../../../utils/defaultConfig';

const project = mockProject as any;

const mockConfig = {
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
  project,
  config: mockConfig,
  currentLang: 'fi',
};

test('renders MapProjectCard component', () => {
  const { container } = render(<MapProjectCard {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders MapProjectCard component with actual project data', () => {
  render(<MapProjectCard {...defaultProps} />);
  expect(screen.getByText('Hitas')).toBeDefined(); // ownership_type
  expect(screen.getByText('Asunto Oy Tuleva S')).toBeDefined(); // housing_company
});

// Test that it has apartments
test('MapProjectCard receives apartments', () => {
  render(<MapProjectCard {...defaultProps} />);
  expect(screen.getByLabelText('SEARCH:aria-apartment-list-for-project', { exact: false })).not.toBeNull();
});
