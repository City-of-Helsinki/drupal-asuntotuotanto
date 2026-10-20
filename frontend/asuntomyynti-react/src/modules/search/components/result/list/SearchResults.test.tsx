import { render, screen } from '@testing-library/react';
import SearchResults from './SearchResults';

const mockSearchResults = [
  {
    id: '123',
    apartments: [],
    street_address: 'test street 1',
    district: 'kallio',
    housing_company: 'company 123',
    image_urls: [],
    main_image_url: '',
    ownership_type: 'hitas',
    url: '',
    completion_estimated_year: 2025,
    completed: false,
    has_future_apartments: false,
    new_apartments_start_date: '',
    users_apartments: [],
    followed_projects: [],
  } as any,
];

const defaultProps = {
  config: undefined,
  header: '',
  currentLang: 'fi',
  searchResults: [],
};

test('renders SearchResults component', () => {
  const { container } = render(<SearchResults {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders ProjectCard list', () => {
  render(<SearchResults {...defaultProps} searchResults={mockSearchResults} />);
  const result = screen.getByText('test street 1');
  expect(result).toBeDefined();
});

test('renders Show on map button', () => {
  render(<SearchResults {...defaultProps} openMap={() => {}} />);
  const element = screen.getByText('SEARCH:show-on-map');
  expect(element).toBeDefined();
});

test('Does not render show on map button if openMap is undefined', () => {
  render(<SearchResults {...defaultProps} searchResults={mockSearchResults} openMap={undefined} />);
  const result = screen.queryByText('SEARCH:show-on-map');
  expect(result).toBeNull();
});
