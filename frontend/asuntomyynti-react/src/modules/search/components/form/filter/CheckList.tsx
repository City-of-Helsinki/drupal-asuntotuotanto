import styles from './CheckList.module.scss';
import { Checkbox, SelectionGroup } from 'hds-react';
import useFilters from '../../../../../hooks/useFilters';
import { useTranslation } from 'react-i18next';

type Props = {
  name: string;
  items: string[];
  label: string;
  isWrapped?: boolean;
  translateItems?: boolean;
};

const CheckList = ({ name, items, label, isWrapped, translateItems = true }: Props) => {
  const { addFilter, removeFilter, getFilters } = useFilters();
  const { t } = useTranslation();

  const selected = getFilters(name);

  const handleChange = (item: string) => () => {
    if (selected.includes(item)) {
      removeFilter(name, item);
    } else {
      addFilter(name, item);
    }
  };

  if (!Array.isArray(items)) {
    return null;
  }

  return (
    <div className={`${styles.container} ${isWrapped ? styles.isWrapped : ''}`}>
      <SelectionGroup label={!isWrapped && label ? label : ''}>
        {items.map((item, index) => {
          const labelText = translateItems ? t(`ES:${item}`) : item;
          return (
            <Checkbox
              key={`${item}-${index}`}
              className={styles.item}
              id={`${label}-${item}-${index}`}
              onChange={handleChange(item)}
              label={labelText}
              checked={selected.includes(item)}
            />
          );
        })}
      </SelectionGroup>
    </div>
  );
};

export default CheckList;
