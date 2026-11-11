import { render } from '@testing-library/react';
import {BrowserRouter} from "react-router-dom";
import Dropdown from "./Dropdown";
import { FilterName } from '../../../../../types/common';

const defaultProps = {
  name: FilterName.RoomCount,
  items: [],
  label: 'Test Dropdown',
  getQuery: () => ({}),
  getLabel: () => 'Test',
  getTagLabel: () => [],
  type: 'select' as any,
};

test('renders Dropdown component', () => {
  const { container } = render(
    <BrowserRouter>
      <Dropdown {...defaultProps} />
    </BrowserRouter>
  );
  const element = container.firstChild;
  expect(element).toBeDefined();
});
