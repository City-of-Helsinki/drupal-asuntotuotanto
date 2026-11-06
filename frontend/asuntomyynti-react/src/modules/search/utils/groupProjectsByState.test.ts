import { groupProjectsByState } from './groupProjectsByState';
import { StateOfSale } from '../../../types/common';

describe('groupProjectsByState', () => {
  it('should', () => {
    const mockData = [
      { state_of_sale: StateOfSale.ForSale },
      { state_of_sale: StateOfSale.ForSale },
      { state_of_sale: StateOfSale.PreMarketing },
    ];
    // @ts-ignore
    const { FOR_SALE, PRE_MARKETING } = groupProjectsByState(mockData);
    expect(FOR_SALE).toHaveLength(2);
    expect(PRE_MARKETING).toHaveLength(1);
  });
});
