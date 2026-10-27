import { render } from '@testing-library/react';
import { BrowserRouter } from 'react-router-dom';
import RangeInput from './RangeInput';

test('renders RangeInput component', () => {
  const { container } = render(
    <BrowserRouter>
      <RangeInput name={'Sami'} from={{ label: 'Sami' }} to={{ label: 'Sami' }} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});
