import { TextInput } from 'hds-react';
import { FilterItem, FilterName } from '../../../../../types/common';
import useFilters from '../../../../../hooks/useFilters';
import styles from './PriceInput.module.scss';

type Props = {
  name: FilterName;
  isWrapped?: boolean;
  label: string;
  items: FilterItem[];
};

const PriceInput = ({ name, label }: Props) => {
  const { getFilter, setFilter } = useFilters();

  const value = getFilter(name) || '';

  const handleChange = (event: React.ChangeEvent<HTMLInputElement>) => {
    setFilter(name, event.target.value || '');
  };

  return (
    <TextInput
      id={`${name}-${label}`}
      label={label}
      value={value}
      onChange={handleChange}
      className={styles.priceInput}
    />
  );
};

export default PriceInput;
