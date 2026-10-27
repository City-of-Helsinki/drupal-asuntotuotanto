import CheckList from './CheckList';
import RangeInput from './RangeInput';
import { FilterItem } from '../../../../../types/common';
import styles from './FloorCombinedFilter.module.scss';

type Props = {
  floorPropertyItems: string[];
  floorRangeFrom: FilterItem;
  floorRangeTo: FilterItem;
};

const FloorCombinedFilter = ({ floorPropertyItems, floorRangeFrom, floorRangeTo }: Props) => {
  return (
    <>
      <RangeInput name="floor" from={floorRangeFrom} to={floorRangeTo} />
      <div className={styles.checkListSection}>
        <CheckList name="properties" label="" isWrapped items={floorPropertyItems} />
      </div>
    </>
  );
};

export default FloorCombinedFilter;
