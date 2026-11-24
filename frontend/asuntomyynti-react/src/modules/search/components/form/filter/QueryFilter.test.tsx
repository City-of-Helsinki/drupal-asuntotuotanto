import { render } from '@testing-library/react';
import {BrowserRouter} from "react-router-dom";
import QueryFilter from "./QueryFilter";
import { FilterName } from '../../../../../types/common';

test('renders QueryFilter component', () => {
  const { container } = render(
    <BrowserRouter>
      <QueryFilter 
        name={FilterName.Price} 
        label="Price" 
        items={[]} 
        type={'range' as any}
        getQuery={() => ({})}
        getLabel={() => ''}
        getTagLabel={() => []}
      />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});
