import { useEffect, useState } from 'react';

type Props<T> = {
  defaultValue: T;
  key: string;
};

const useSessionStorageState = <T,>({ defaultValue, key }: Props<T>) => {
  const [value, setValue] = useState<T>(() => {
    const storageValue = sessionStorage.getItem(key);
    return storageValue !== null && storageValue !== undefined
      ? (JSON.parse(storageValue) as T)
      : defaultValue;
  });

  useEffect(() => {
    if (value === undefined) {
      sessionStorage.removeItem(key);
    } else {
      sessionStorage.setItem(key, JSON.stringify(value));
    }
  }, [key, value]);

  return [value, setValue] as const;
};

export default useSessionStorageState;
