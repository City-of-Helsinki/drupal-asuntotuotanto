import { calculateApartmentCount } from './calculateApartmentCount';
import mockProject from '../mocks/single-project.json';

test('returns 0 when no projects given', () => {
  const result = calculateApartmentCount([], 'fi');
  expect(result).toEqual(0);
});

test('returns 0 when no apartments in given project', () => {
  const projectWithoutApartments = { ...mockProject, apartments: [] };
  const result = calculateApartmentCount([projectWithoutApartments], 'fi');
  expect(result).toEqual(0);
});

test('returns number of apartments by given language', () => {
  const result = calculateApartmentCount([mockProject], 'fi');
  expect(result).toEqual(1);
});
