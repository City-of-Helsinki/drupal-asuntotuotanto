import { DefaultFilterConfig, FilterConfig, FilterName, FilterType, QueryParams } from '../../../types/common';
import { formatRange } from './formatRange';
import { groupConsecutiveNumbers, listGroupedNumbers } from '../../../utils/groupConsecutiveNumbers';

type FilterMap = {
  [key in FilterName]: (config: DefaultFilterConfig) => FilterConfig;
};

const REQUIRED_PROPERTY_OPTIONS = [
  'PROJECT_HAS_SAUNA',
  'HAS_APARTMENT_SAUNA',
  'HAS_BALCONY',
  'HAS_TERRACE',
  'HAS_YARD',
  'TOP_FLOOR',
  'BOTTOM_FLOOR',
  'NOT_TOP_FLOOR',
  'NOT_BOTTOM_FLOOR',
];

const EXCLUDED_PROPERTY_OPTIONS = new Set([
  'SINGLE_LEVEL',
  'HAS_FRENCH_BALCONY',
  'FRENCH_BALCONY',
  'PROJECT_HAS_ELEVATOR',
]);

const normalizePropertyOption = (option: string) => option.trim().toUpperCase();

const filterMap: FilterMap = {
  project_district: (config) => ({
    ...config,
    type: FilterType.MultiSelect,
    icon: 'location',
    getLabel: (values: string[]) => {
      const [firstItem] = values;
      if (!firstItem) {
        return '';
      }
      return firstItem + (values.length > 1 ? ` +${values.length - 1}` : '');
    },
  }),

  project_district_haso: (config) => ({
    ...config,
    type: FilterType.MultiSelect,
    icon: 'location',
    getLabel: (values: string[]) => {
      const [firstItem] = values;
      if (!firstItem) {
        return '';
      }
      return firstItem + (values.length > 1 ? ` +${values.length - 1}` : '');
    },
  }),

  project_district_hitas: (config) => ({
    ...config,
    type: FilterType.MultiSelect,
    icon: 'location',
    getLabel: (values: string[]) => {
      const [firstItem] = values;
      if (!firstItem) {
        return '';
      }
      return firstItem + (values.length > 1 ? ` +${values.length - 1}` : '');
    },
  }),

  room_count: ({ suffix = '', items, ...rest }) => ({
    ...rest,
    items: items.map((item) => `${item} ${suffix}`),
    type: FilterType.MultiSelect,
    icon: 'home',
    translateItems: false,
    getQuery: (values) => {
      const filters: QueryParams = {
        [FilterName.RoomCount]: values.map((x) => parseInt(x)),
      };
      return filters;
    },
    getLabel: (values) => {
      const groupedNumbers = groupConsecutiveNumbers(values.map((x) => parseInt(x)));
      return listGroupedNumbers(groupedNumbers, (_first, last) =>
        last === 5 ? ` ${suffix}, 5+ ${suffix}` : ` ${suffix}`
      );
    },
  }),

  living_area: ({ items: [from, to], suffix, ...rest }) => ({
    ...rest,
    type: FilterType.Range,
    items: [
      { label: from || '', placeholder: suffix || '' },
      { label: to || '', placeholder: suffix || '' },
    ],
    getLabel: (values) => {
      return formatRange(values);
    },
    getTagLabel: (serializedValue) => {
      const formattedRange = formatRange(serializedValue.split(','));
      return [[FilterName.LivingArea, formattedRange]];
    },
  }),

  price: ({ items: [label], suffix, ...rest }) => ({
    ...rest,
    type: FilterType.Input,
    items: [
      {
        label,
        placeholder: suffix || '',
      },
    ],
    getQuery: ([value]) => {
      return { [FilterName.Price]: parseInt(value) * 100000 };
    },
    getLabel: ([value]) => {
      return `max. ${value} 000 ${suffix}`;
    },
    getTagLabel: (value) => [[FilterName.Price, value, `max. ${value} 000 ${suffix}`]],
  }),

  project_building_type: (config) => ({
    ...config,
    type: FilterType.MultiSelect,
  }),

  properties: ({ items, ...rest }) => {
    const safeItems = Array.isArray(items) ? items : [];

    const filteredItems = safeItems.filter(
      (item) => item && !EXCLUDED_PROPERTY_OPTIONS.has(normalizePropertyOption(item))
    );

    const mergedItems = [...filteredItems];
    REQUIRED_PROPERTY_OPTIONS.forEach((requiredOption) => {
      const hasOption = mergedItems.some((item) => normalizePropertyOption(item) === requiredOption);
      if (!hasOption) {
        mergedItems.push(requiredOption);
      }
    });

    return {
      ...rest,
      items: mergedItems,
      type: FilterType.MultiSelect,
    };
  },

  project_state_of_sale: (config) => ({
    ...config,
    type: FilterType.MultiSelect,
  }),
};

export default filterMap;
