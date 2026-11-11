import { withApartmentParam } from './withApartmentParam';

test('appends the apartment query parameter to a url without a query string', () => {
  expect(withApartmentParam('https://example.com/application/add/hitas/15', 84)).toEqual(
    'https://example.com/application/add/hitas/15?apartment=84'
  );
});

test('appends the apartment query parameter to a url that already has a query string', () => {
  expect(withApartmentParam('https://example.com/application/add/hitas/15?foo=bar', 84)).toEqual(
    'https://example.com/application/add/hitas/15?foo=bar&apartment=84'
  );
});

test('replaces an existing apartment query parameter', () => {
  expect(withApartmentParam('https://example.com/application/add/hitas/15?apartment=1', 84)).toEqual(
    'https://example.com/application/add/hitas/15?apartment=84'
  );
});

test('preserves a URL fragment when appending the apartment parameter', () => {
  expect(withApartmentParam('https://example.com/application/add/hitas/15#form', 84)).toEqual(
    'https://example.com/application/add/hitas/15?apartment=84#form'
  );
});

test('preserves a fragment when the url already has a query string', () => {
  expect(withApartmentParam('https://example.com/application/add/hitas/15?foo=bar#form', 84)).toEqual(
    'https://example.com/application/add/hitas/15?foo=bar&apartment=84#form'
  );
});

test('returns the url unchanged when the url is empty', () => {
  expect(withApartmentParam('', 84)).toEqual('');
});
