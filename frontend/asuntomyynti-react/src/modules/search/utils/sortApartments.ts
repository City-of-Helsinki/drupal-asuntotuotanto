import React from 'react';
import useSessionStorageState from '../../../hooks/useSessionStorageState';

type SortConfig = {
  key: string;
  direction: 'ascending' | 'descending';
  alphaNumeric: boolean;
};

const SortApartments = <T extends Record<string, unknown>>(items: T[], sessionStorageID: string) => {
  const sortDefaultProps: SortConfig = {
    key: 'apartment_number',
    direction: 'ascending',
    alphaNumeric: true,
  };
  const [sortConfig, setSortConfig] = useSessionStorageState<SortConfig>({
    defaultValue: sortDefaultProps,
    key: `sortConfig-${sessionStorageID}`,
  });

  const sortedApartments = React.useMemo(() => {
    const sortableApartments = [...items];

    if (sortConfig !== null) {
      if (sortConfig.alphaNumeric) {
        sortableApartments.sort((a, b) => {
          // Handle edge case where apartment can miss `apartment_number` by falling back to nid.
          const firstRaw = a[sortConfig.key] ?? a['nid'] ?? '';
          const secondRaw = b[sortConfig.key] ?? b['nid'] ?? '';
          const firstValue = String(firstRaw).split(' ').join('');
          const secondValue = String(secondRaw).split(' ').join('');
          if (sortConfig.direction === 'ascending') {
            return firstValue.localeCompare(secondValue, 'fi', { numeric: true });
          }
          return secondValue.localeCompare(firstValue, 'fi', { numeric: true });
        });
      } else {
        sortableApartments.sort((a, b) => {
          const firstValue = Number(a[sortConfig.key] ?? 0);
          const secondValue = Number(b[sortConfig.key] ?? 0);

          if (firstValue < secondValue) {
            return sortConfig.direction === 'ascending' ? -1 : 1;
          }
          if (firstValue > secondValue) {
            return sortConfig.direction === 'ascending' ? 1 : -1;
          }
          return 0;
        });
      }
    }
    return sortableApartments;
  }, [items, sortConfig]);

  const requestSort = (key: string, alphaNumeric: boolean) => {
    let direction: SortConfig['direction'] = 'ascending';
    if (sortConfig && sortConfig.key === key && sortConfig.direction === 'ascending') {
      direction = 'descending';
    }
    setSortConfig({ key, direction, alphaNumeric });
  };

  return { items: sortedApartments, requestSort, sortConfig };
};

export default SortApartments;
