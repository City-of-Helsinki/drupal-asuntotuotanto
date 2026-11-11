import { getProjectApplicationStatus, getApartmentApplicationStatus } from './getApplicationStatus';

const mockProjectApplicationStatusConfig = {
  20: {
    5: 'LOW',
    7: 'MEDIUM',
    9: 'HIGH',
    12: 'LOW',
    15: 'HIGH',
  },
};

const mockApartmentApplicationStatusConfig = {
  5: 'LOW',
  7: 'MEDIUM',
  9: 'HIGH',
  12: 'LOW',
  15: 'HIGH',
};

test('getProjectApplicationStatus returns undefined if status is not given', () => {
  const result = getProjectApplicationStatus(undefined, 0);
  expect(result).toBeUndefined();
});

test('getProjectApplicationStatus returns status to the given project id', () => {
  const result = getProjectApplicationStatus(mockProjectApplicationStatusConfig, 20);
  expect(result).toEqual({ '12': 'LOW', '15': 'HIGH', '5': 'LOW', '7': 'MEDIUM', '9': 'HIGH' });
});

test('getProjectApplicationStatus returns undefined to the given project id', () => {
  const result = getProjectApplicationStatus(mockProjectApplicationStatusConfig, 10);
  expect(result).toBeUndefined();
});

test('getApartmentApplicationStatus returns undefined if status is not given', () => {
  const result = getApartmentApplicationStatus(undefined, 0);
  expect(result).toBeUndefined();
});

test('getApartmentApplicationStatus returns status for the given apartment id', () => {
  const result = getApartmentApplicationStatus(mockApartmentApplicationStatusConfig, 9);
  expect(result).toEqual('HIGH');
});

test('getApartmentApplicationStatus returns undefined if there are no status for the given apartment id', () => {
  const result = getApartmentApplicationStatus(mockApartmentApplicationStatusConfig, 99);
  expect(result).toBeUndefined();
});
