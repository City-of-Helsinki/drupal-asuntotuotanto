import type { Project } from '../../../types/common';

const filterApartmentA0 = (results: Project[]) => {
  results = results.slice();
  for (const result of results) {
    const apartments = result.apartments.slice();
    result.apartments = apartments.filter((apartment) => apartment.apartment_number?.toUpperCase() !== 'A0');
  }
  return results;
};

export default filterApartmentA0;
