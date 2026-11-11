import { render, screen } from '@testing-library/react';
import MapApartmentRow from './MapApartmentRow';

import mockApartment from '../../../mocks/single-apartment.json';

const defaultProps = {
  apartment: mockApartment as any,
  userApplications: undefined,
  applicationStatus: undefined,
  userHasApplicationForProject: false,
  userHasReservedOrSoldApartmentInProject: false,
  isMobileSize: false,
  projectOwnershipIsHaso: false,
};

test('renders MapApartmentRow component', () => {
  const { container } = render(<MapApartmentRow {...defaultProps} />);
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders apartment details', () => {
  render(<MapApartmentRow {...defaultProps} />);

  expect(screen.queryByText('A15')).not.toBeNull(); // apartment_number
  expect(screen.queryByText('1h+k+s')).not.toBeNull(); // apartment_structure
});

test('does not show "free" when application status is RESERVED for free_for_reservations apartment', () => {
  const apartment = {
    ...mockApartment,
    apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
    project_application_start_time: '2100-01-01T00:00:00+02:00',
    project_application_end_time: '2100-01-10T00:00:00+02:00',
  };

  render(<MapApartmentRow {...defaultProps} apartment={apartment} applicationStatus="RESERVED" />);

  expect(screen.getByText('SEARCH:apartment-reserved')).toBeInTheDocument();
  expect(screen.queryByText('SEARCH:apartment-free')).not.toBeInTheDocument();
});

test('does not show "free" when application status is SOLD for free_for_reservations apartment', () => {
  const apartment = {
    ...mockApartment,
    apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
    project_application_start_time: '2100-01-01T00:00:00+02:00',
    project_application_end_time: '2100-01-10T00:00:00+02:00',
  };

  render(<MapApartmentRow {...defaultProps} apartment={apartment} applicationStatus="SOLD" />);

  expect(screen.getByText('SEARCH:apartment-sold')).toBeInTheDocument();
  expect(screen.queryByText('SEARCH:apartment-free')).not.toBeInTheDocument();
});

describe('SEARCH:make-reservation button', () => {
  const inThePast = '2000-01-01T00:00:00.000Z';

  const freeAfterPeriod = {
    ...mockApartment,
    apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
    application_url: '',
    project_application_start_time: inThePast,
    project_application_end_time: inThePast,
    project_can_apply_afterwards: true,
    can_apply_afterwards: true,
  };

  test('links to the application form with the apartment preselected', () => {
    const { container } = render(
      <MapApartmentRow {...defaultProps} apartment={freeAfterPeriod} projectOwnershipIsHaso={false} />
    );

    const expectedUrl = `${window.location.origin}/application/add/hitas/${mockApartment.project_id}?apartment=${mockApartment.nid}`;
    expect(container.querySelector(`a[href="${expectedUrl}"]`)).toBeInTheDocument();
  });

  test('uses puolihitas ownership in the built application URL', () => {
    const apt = {
      ...freeAfterPeriod,
      project_ownership_type: 'puolihitas',
    };

    const { container } = render(<MapApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    const expectedUrl = `${window.location.origin}/application/add/puolihitas/${mockApartment.project_id}?apartment=${mockApartment.nid}`;
    expect(container.querySelector(`a[href="${expectedUrl}"]`)).toBeInTheDocument();
  });

  test('does not show SEARCH:make-reservation for HASO apartments', () => {
    const apt = {
      ...freeAfterPeriod,
      project_ownership_type: 'haso',
    };

    render(<MapApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={true} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
    expect(screen.getByText('SEARCH:after-apply')).toBeInTheDocument();
  });

  test('does not show SEARCH:make-reservation when project_can_apply_afterwards is false', () => {
    const apt = {
      ...freeAfterPeriod,
      project_can_apply_afterwards: false,
      can_apply_afterwards: false,
    };

    render(<MapApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation when user already has reserved/sold apartment', () => {
    render(
      <MapApartmentRow
        {...defaultProps}
        apartment={freeAfterPeriod}
        projectOwnershipIsHaso={false}
        userHasReservedOrSoldApartmentInProject={true}
      />
    );

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation for RESERVED apartments', () => {
    const apt = {
      ...freeAfterPeriod,
      apartment_state_of_sale: 'RESERVED',
    };

    render(<MapApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation for SOLD apartments', () => {
    const apt = {
      ...freeAfterPeriod,
      apartment_state_of_sale: 'SOLD',
    };

    render(<MapApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });
});
