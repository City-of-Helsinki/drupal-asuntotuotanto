import { formatRange } from './formatRange';

test('returns "x-x m2" if both min and max are given', () => {
  const result = formatRange(['1', '2']);
  expect(result).toEqual('1-2 m\u00b2');
});

test('returns "> x m2" if only min is given', () => {
  const result = formatRange(['1', '']);
  expect(result).toEqual('> 1 m\u00b2');
});

test('returns "< x m2" if only max is given', () => {
  const result = formatRange(['', '2']);
  expect(result).toEqual('< 2 m\u00b2');
});

test('returns an empty string if no min and max given', () => {
  const result = formatRange(['', '']);
  expect(result).toEqual('');
});
