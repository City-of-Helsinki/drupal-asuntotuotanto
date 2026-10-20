import { groupConsecutiveNumbers, listGroupedNumbers } from './groupConsecutiveNumbers';

describe('groupConsecutiveNumbers', function () {
  it('should group numbers', () => {
    const subject = [1, 3, 5, 2, 6, 8];
    expect(groupConsecutiveNumbers(subject)).toEqual([[1, 2, 3], [5, 6], [8]]);
  });
});

describe('listGroupedNumbers', function () {
  it('should list grouped numbers', () => {
    const subject = [[1, 2, 3], [5, 6], [8]];
    expect(listGroupedNumbers(subject)).toEqual('1-3, 5-6, 8');
  });
});
