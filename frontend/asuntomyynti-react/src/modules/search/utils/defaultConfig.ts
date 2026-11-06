import { DefaultFilterConfig, FilterName } from '../../../types/common';

export const defaultConfig = (name: FilterName): DefaultFilterConfig => ({
  label: name,
  suffix: null,
  items: [],
  // eslint-disable-next-line @typescript-eslint/no-unused-vars
  getQuery: (_values: string[]) => {
    return {};
  },
  getLabel: (values: string[]) => {
    return values.join(', ');
  },
  getTagLabel: (serializedValue: string) =>
    serializedValue
      .split(',')
      .filter((value) => value !== '') // Filter out empty values
      .map((value) => [name, value]),
});
