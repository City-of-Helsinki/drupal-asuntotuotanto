import { render, screen } from '@testing-library/react';
import Label from './Label';
import styles from './Label.module.scss';

test('renders label', () => {
  render(<Label children={'test'} />);

  expect(screen.getByText('test')).toBeDefined();
});

test('renders label for hitas', () => {
  render(<Label children={'test'} type={'hitas'} />);

  const elem = screen.getByText('test');

  expect(elem.classList.contains(styles.hitas)).toBe(true);
});

test('renders label for haso', () => {
  render(<Label children={'test'} type={'haso'} />);

  const elem = screen.getByText('test');

  expect(elem.classList.contains(styles.haso)).toBe(true);
});

test('renders label for puolihitas', () => {
  render(<Label children={'test'} type={'puolihitas'} />);

  const elem = screen.getByText('test');

  expect(elem.classList.contains(styles.puolihitas)).toBe(true);
});

test('renders label with unknown type', () => {
  render(<Label children={'test'} type={'foo'} />);

  const elem = screen.getByText('test');

  expect(elem.classList.contains('foo')).toBe(false);
});
