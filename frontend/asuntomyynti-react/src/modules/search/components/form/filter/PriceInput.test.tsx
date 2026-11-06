import { render, screen } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import PriceInput from './PriceInput';
import { FilterName } from '../../../../../types/common';

test('renders PriceInput with correct label', () => {
  render(
    <BrowserRouter>
      <PriceInput name={FilterName.Price} label={'testLabel'} items={[]} />
    </BrowserRouter>
  );

  expect(screen.getByLabelText('testLabel')).toBeDefined();
});
