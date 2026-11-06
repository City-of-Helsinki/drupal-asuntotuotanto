import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import ApartmentRow from './ApartmentRow';

import mockApartment from '../../../mocks/single-apartment.json';
import statusMocks from '../../../mocks/application-status-apartments.json';

const { apartments, apartment_application_status } = statusMocks as any;

function buildPropsFor(
  nid: number,
  apartmentOverrides: Partial<React.ComponentProps<typeof ApartmentRow>['apartment']> = {},
  extraProps: Partial<React.ComponentProps<typeof ApartmentRow>> = {}
) {
  const apartment = apartments.find((a: any) => a.nid === nid);
  if (!apartment) {
    throw new Error(`No apartment with nid ${nid} in application-status-apartments.json`);
  }

  const status = apartment_application_status[String(nid)];

  return {
    apartment: { ...apartment, ...apartmentOverrides } as any,
    applicationStatus: status,
    userApplications: [],
    userHasApplicationForProject: false,
    userHasReservedOrSoldApartmentInProject: false,
    projectOwnershipIsHaso: apartment.project_ownership_type === 'haso',
    ...extraProps,
  } as React.ComponentProps<typeof ApartmentRow>;
}

const defaultProps = {
  apartment: mockApartment as any,
  userApplications: [],
  applicationStatus: undefined,
  userHasApplicationForProject: false,
  userHasReservedOrSoldApartmentInProject: false,
  projectOwnershipIsHaso: false,
};

test('renders ApartmentRow component', () => {
  const { container } = render(
    <BrowserRouter>
      <ApartmentRow {...defaultProps} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});

test('renders apartment details', () => {
  render(
    <BrowserRouter>
      <ApartmentRow {...defaultProps} />
    </BrowserRouter>
  );

  expect(screen.queryByText('A15')).not.toBeNull(); // apartment_number
  expect(screen.queryByText('1h+k+s')).not.toBeNull(); // apartment_structure
});

describe('SEARCH:apply / SEARCH:after-apply buttons', () => {
  const inThePast = '2000-01-01T00:00:00.000Z';
  const inTheFuture = '2100-01-01T00:00:00.000Z';

  test('shows SEARCH:apply during application period when user has no reserved/sold apartment', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
      can_apply_afterwards: false,
      project_can_apply_afterwards: false,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.getByText('SEARCH:apply')).toBeInTheDocument();
  });

  test('shows SEARCH:after-apply for HASO when can_apply_afterwards is true outside application period', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      can_apply_afterwards: true,
      project_can_apply_afterwards: true,
      project_ownership_type: 'haso',
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={true} />);

    expect(screen.getByText('SEARCH:after-apply')).toBeInTheDocument();
  });

  test('shows SEARCH:make-reservation not SEARCH:after-apply for free HITAS after period', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      can_apply_afterwards: true,
      project_can_apply_afterwards: true,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.getByText('SEARCH:make-reservation')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:after-apply')).toBeNull();
  });

  test('shows SEARCH:apply not SEARCH:after-apply for HITAS during period even when can_apply_afterwards is true', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
      can_apply_afterwards: true,
      project_can_apply_afterwards: true,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.getByText('SEARCH:apply')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:after-apply')).toBeNull();
    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:apply / SEARCH:after-apply when user has reserved or sold apartment in project', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
      can_apply_afterwards: false,
      project_can_apply_afterwards: false,
    };

    render(
      <ApartmentRow
        {...defaultProps}
        apartment={apt}
        projectOwnershipIsHaso={false}
        userHasReservedOrSoldApartmentInProject={true}
      />
    );

    expect(screen.queryByText('SEARCH:apply')).toBeNull();
    expect(screen.queryByText('SEARCH:after-apply')).toBeNull();
  });

  test('does not show SEARCH:apply / SEARCH:after-apply when outside period, not free, and cannot apply afterwards', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'SOME_OTHER_STATE',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      can_apply_afterwards: false,
      project_can_apply_afterwards: false,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:apply')).toBeNull();
    expect(screen.queryByText('SEARCH:after-apply')).toBeNull();
  });
});

test('renders contact us links correctly for apartments', () => {
  const apt = {
    ...mockApartment,
    apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
  };
  const { container } = render(<ApartmentRow {...defaultProps} apartment={apt} />);
  const expectedContactUsLink = `${window.location.origin}/contact/apply_for_free_apartment?apartment=${mockApartment.apartment_number}&project=${mockApartment.project_id}`;

  const contactUsLink = container.querySelector(`a[href="${expectedContactUsLink}"]`);
  expect(contactUsLink).toBeInTheDocument();
});

describe('SEARCH:make-reservation button — HITAS post-period free apartments', () => {
  const inThePast = '2000-01-01T00:00:00.000Z';
  const inTheFuture = '2100-01-01T00:00:00.000Z';

  test('shows SEARCH:make-reservation for free HITAS apartment after period with can_apply_afterwards', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.getByText('SEARCH:make-reservation')).toBeInTheDocument();
  });

  test('does not show SEARCH:make-reservation when can_apply_afterwards is false', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: false,
      can_apply_afterwards: false,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation when can_apply_afterwards is true but project_can_apply_afterwards is false', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      can_apply_afterwards: true,
      project_can_apply_afterwards: false,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation when application period has not started yet', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inTheFuture,
      project_application_end_time: inTheFuture,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation when application dates are empty', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: '',
      project_application_end_time: '',
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('does not show SEARCH:make-reservation for HASO apartments (uses SEARCH:after-apply instead)', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
      project_ownership_type: 'haso',
    };

    render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={true} />);

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
    expect(screen.getByText('SEARCH:after-apply')).toBeInTheDocument();
  });

  test('does not show SEARCH:make-reservation when user already has reserved/sold apartment in project', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    render(
      <ApartmentRow
        {...defaultProps}
        apartment={apt}
        projectOwnershipIsHaso={false}
        userHasReservedOrSoldApartmentInProject={true}
      />
    );

    expect(screen.queryByText('SEARCH:make-reservation')).toBeNull();
  });

  test('still shows contact us for free HITAS apartment when can_apply_afterwards is false', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: false,
      can_apply_afterwards: false,
    };

    const { container } = render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    const expectedContactUsLink = `${window.location.origin}/contact/apply_for_free_apartment?apartment=${mockApartment.apartment_number}&project=${mockApartment.project_id}`;
    const contactUsLink = container.querySelector(`a[href="${expectedContactUsLink}"]`);
    expect(contactUsLink).toBeInTheDocument();
  });

  test('SEARCH:make-reservation links to /application/add/hitas/{project_id} with the apartment preselected', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    const { container } = render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    const expectedUrl = `${window.location.origin}/application/add/hitas/${mockApartment.project_id}?apartment=${mockApartment.nid}`;
    const reservationLink = container.querySelector(`a[href="${expectedUrl}"]`);
    expect(reservationLink).toBeInTheDocument();
  });

  test('SEARCH:make-reservation ignores a stale application_url and builds from indexed fields', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
      // Stale ES value that must not be used after the period ends.
      application_url: 'https://example.com/contact/apply_for_free_apartment',
      project_application_start_time: inThePast,
      project_application_end_time: inThePast,
      project_can_apply_afterwards: true,
      can_apply_afterwards: true,
    };

    const { container } = render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    const expectedUrl = `${window.location.origin}/application/add/hitas/${mockApartment.project_id}?apartment=${mockApartment.nid}`;
    expect(container.querySelector(`a[href="${expectedUrl}"]`)).toBeInTheDocument();
    expect(container.querySelector('a[href*="contact/apply_for_free_apartment"]')).toBeNull();
  });

  test('SEARCH:apply button includes the apartment query parameter', () => {
    const apt = {
      ...mockApartment,
      apartment_state_of_sale: 'OPEN_FOR_APPLICATIONS',
      application_url: '',
      project_application_start_time: inThePast,
      project_application_end_time: '2100-01-01T00:00:00.000Z',
      project_can_apply_afterwards: false,
      can_apply_afterwards: false,
    };

    const { container } = render(<ApartmentRow {...defaultProps} apartment={apt} projectOwnershipIsHaso={false} />);

    const expectedUrl = `${window.location.origin}/application/add/hitas/${mockApartment.project_id}?apartment=${mockApartment.nid}`;
    expect(container.querySelector(`a[href="${expectedUrl}"]`)).toBeInTheDocument();
  });
});

describe('ApartmentRow customer-facing status outside application period', () => {
  test('shows free for a vacant apartment', () => {
    const props = buildPropsFor(101, {
      project_application_start_time: '2100-01-01T00:00:00+02:00',
      project_application_end_time: '2100-01-10T00:00:00+02:00',
    });

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-free')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-few-applications')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-some-applications')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-lots-of-applications')).not.toBeInTheDocument();
  });

  test.each([102, 103])('shows reserved for reserved apartment (nid=%s)', (nid) => {
    const props = buildPropsFor(nid, {
      project_application_start_time: '2100-01-01T00:00:00+02:00',
      project_application_end_time: '2100-01-10T00:00:00+02:00',
    });

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-reserved')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-few-applications')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-some-applications')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-lots-of-applications')).not.toBeInTheDocument();
  });

  test('does not show "free" when application status is RESERVED for free_for_reservations apartment', () => {
    const props = buildPropsFor(
      101,
      {
        apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
        project_application_start_time: '2100-01-01T00:00:00+02:00',
        project_application_end_time: '2100-01-10T00:00:00+02:00',
      },
      { applicationStatus: 'RESERVED' }
    );

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-reserved')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-free')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:contact-us')).not.toBeInTheDocument();
  });

  test('does not show "free" when application status is SOLD for free_for_reservations apartment', () => {
    const props = buildPropsFor(
      101,
      {
        apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
        project_application_start_time: '2100-01-01T00:00:00+02:00',
        project_application_end_time: '2100-01-10T00:00:00+02:00',
      },
      { applicationStatus: 'SOLD' }
    );

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-sold')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-free')).not.toBeInTheDocument();
    expect(screen.queryByText('SEARCH:contact-us')).not.toBeInTheDocument();
  });

  test('free_for_reservations during active period keeps application-status messaging', () => {
    const props = buildPropsFor(
      101,
      {
        apartment_state_of_sale: 'FREE_FOR_RESERVATIONS',
        project_application_start_time: '2000-01-01T00:00:00.000Z',
        project_application_end_time: '2100-01-01T00:00:00.000Z',
      },
      { applicationStatus: 'LOW' }
    );

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-few-applications')).toBeInTheDocument();
    expect(screen.queryByText('SEARCH:apartment-free')).not.toBeInTheDocument();
  });
});

describe('ApartmentRow status during application period (few/many applicants)', () => {
  const inThePast = '2000-01-01T00:00:00.000Z';
  const inTheFuture = '2100-01-01T00:00:00.000Z';

  test('LOW → few applications during application period', () => {
    const props = buildPropsFor(105, {
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
    });

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-few-applications')).toBeInTheDocument();
  });

  test('MEDIUM → some applications during application period', () => {
    const props = buildPropsFor(106, {
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
    });

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-some-applications')).toBeInTheDocument();
  });

  test('HIGH → lots of applications during application period', () => {
    const props = buildPropsFor(107, {
      project_application_start_time: inThePast,
      project_application_end_time: inTheFuture,
    });

    render(<ApartmentRow {...props} />);

    expect(screen.getByText('SEARCH:apartment-lots-of-applications')).toBeInTheDocument();
  });
});
