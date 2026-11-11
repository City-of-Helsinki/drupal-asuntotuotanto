import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ApartmentTable from './ApartmentTable';

import mockApartment from '../../../mocks/single-apartment.json';

const apartment = mockApartment as any;

const defaultProps = {
  apartments: [apartment],
  applications: [],
  applicationStatus: {},
  housingCompany: 'Test Co',
  projectID: 123,
  projectOwnershipIsHaso: false,
  userHasApplicationForProject: false,
};

test('renders ApartmentTable component', () => {
  const { container } = render(
    <BrowserRouter>
      <ApartmentTable {...defaultProps} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders table header elements', () => {
  render(
    <BrowserRouter>
      <ApartmentTable {...defaultProps} />
    </BrowserRouter>
  );
  expect(screen.getAllByText('SEARCH:apartment')).toBeDefined();
  expect(screen.getAllByText('SEARCH:floor')).toBeDefined();
  expect(screen.getAllByText('SEARCH:area')).toBeDefined();
  expect(screen.getAllByText('SEARCH:free-of-debt-price')).toBeDefined();
  expect(screen.getAllByText('SEARCH:applications')).toBeDefined();
});

test('Dont render pagination', () => {
  render(
    <BrowserRouter>
      <ApartmentTable {...defaultProps} />
    </BrowserRouter>
  );
  expect(screen.queryByLabelText('SEARCH:previous-page')).toBeNull();
});

test('Renders pagination', () => {
  const multipleApartments = new Array(15);

  for (let i = 0; i < multipleApartments.length; i++) {
    multipleApartments[i] = { ...apartment, uuid: `mock-${i}` };
  }

  render(
    <BrowserRouter>
      <ApartmentTable {...defaultProps} apartments={multipleApartments} />
    </BrowserRouter>
  );
  expect(screen.queryByLabelText('SEARCH:previous-page')).not.toBeNull();
});
