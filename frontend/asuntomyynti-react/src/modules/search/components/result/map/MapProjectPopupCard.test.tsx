import { render, screen } from '@testing-library/react';
import MapProjectPopupCard from './MapProjectPopupCard';

import mockProject from '../../../mocks/single-project.json';
import { FilterName } from '../../../../../types/common';
import { defaultConfig } from '../../../utils/defaultConfig';

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
  config: mockConfig,
  project: mockProject as any,
  currentLang: 'fi',
  activeProject: undefined,
  onCloseBtnClick: () => {},
  onApartmentsBtnClick: () => {},
};

test('renders MapProjectPopupCard component', () => {
  const { container } = render(<MapProjectPopupCard {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders MapProjectPopupCard component with actual project data', () => {
  render(<MapProjectPopupCard {...defaultProps} />);
  expect(screen.getByText('Hitas')).toBeDefined(); // ownership_type
  expect(screen.getByText('Asunto Oy Tuleva S')).toBeDefined(); // housing_company
});

test('renders "show all apartments" button when hideApartments is false', () => {
  render(<MapProjectPopupCard {...defaultProps} hideApartments={false} />);
  expect(screen.queryByText(/apartments/i)).not.toBeNull();
});

test('Does not show "show all apartments" button when hideApartments is true', () => {
  render(<MapProjectPopupCard {...defaultProps} hideApartments />);
  expect(screen.queryByText(/apartments/i)).toBeNull();
});
