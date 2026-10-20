import { ApplicationStatus, DataConfig } from '../../../types/common';
import {
  userHasApplications,
  getUserApplications,
  userHasReservedOrSoldApartment,
} from './userApplications';

const mockUserConfig = {
  user_id: '0',
  email_address: null,
  username: '',
  applications: {
    20: [5, 7, 9, 12, 15],
  },
};

test('userHasApplications returns false if user is not given', () => {
  const result = userHasApplications(undefined, 0);
  expect(result).toBe(false);
});

test('userHasApplications has applications to the given project id', () => {
  const result = userHasApplications(mockUserConfig, 20);
  expect(result).toBe(true);
});

test('userHasApplications does not have applications to the given project id', () => {
  const result = userHasApplications(mockUserConfig, 10);
  expect(result).toBe(false);
});

test('getUserApplications returns undefined if user is not given', () => {
  const result = getUserApplications(undefined, 0);
  expect(result).toBeUndefined();
});

test('getUserApplications returns applications for the given project id', () => {
  const result = getUserApplications(mockUserConfig, 20);
  expect(result).toEqual([5, 7, 9, 12, 15]);
});

test('getUserApplications returns undefined if there are no applications to the given project id', () => {
  const result = getUserApplications(mockUserConfig, 10);
  expect(result).toBeUndefined();
});

describe('userHasReservedOrSoldApartment', () => {
  const projectId = 20;
  const reservedApartmentId = 9;

  const buildData = (
    apartmentStatuses: Record<number, string>,
    applications: Record<number, number[]> = { [projectId]: [5, reservedApartmentId, 12] }
  ): DataConfig =>
    ({
      filters: {},
      static_content: {},
      token: 'test',
      user: {
        user_id: 1,
        email_address: null,
        username: 'test',
        applications,
        application_project_pairs: [{ project_id: projectId, application_id: 999 }],
        followed_projects: [],
      },
      apartment_application_status: {
        [projectId]: apartmentStatuses,
      },
    }) as unknown as DataConfig;

  test('returns false when data is undefined', () => {
    expect(userHasReservedOrSoldApartment(undefined, projectId)).toBe(false);
  });

  test('returns false when user has no applications for the project', () => {
    const data = buildData(
      { [reservedApartmentId]: ApplicationStatus.Reserved },
      { [projectId]: [] }
    );
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(false);
  });

  test('returns false when user apartments are not reserved or sold', () => {
    const data = buildData({
      5: ApplicationStatus.Low,
      [reservedApartmentId]: ApplicationStatus.Medium,
      12: ApplicationStatus.High,
    });
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(false);
  });

  test('returns true when user has a RESERVED apartment in the project', () => {
    const data = buildData({
      5: ApplicationStatus.Low,
      [reservedApartmentId]: ApplicationStatus.Reserved,
      12: ApplicationStatus.High,
    });
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(true);
  });

  test('returns true when user has a RESERVED_HASO apartment in the project', () => {
    const data = buildData({
      [reservedApartmentId]: ApplicationStatus.ReservedHaso,
    });
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(true);
  });

  test('returns true when user has a SOLD apartment in the project', () => {
    const data = buildData({
      [reservedApartmentId]: ApplicationStatus.Sold,
    });
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(true);
  });

  test('ignores application entity id and uses apartment ids from user applications', () => {
    // application_id 999 must not be treated as an apartment status key.
    const data = buildData({
      999: ApplicationStatus.Sold,
      [reservedApartmentId]: ApplicationStatus.Low,
    });
    expect(userHasReservedOrSoldApartment(data, projectId)).toBe(false);
  });
});
