import { fullURL } from './fullURL';

test('Returns # if path is empty', () => {
  const url = fullURL('');
  expect(url).toEqual('#');
});

test('Returns original path if path starts with http', () => {
  const url = fullURL('http://google.com');
  expect(url).toEqual('http://google.com');
});

test('Returns path with a prefix //', () => {
  const url = fullURL('google.com');
  expect(url).toEqual('//google.com');
});
