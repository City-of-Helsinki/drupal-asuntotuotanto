import { RefObject } from 'react';
import styles from './SearchForm.module.scss';
import { Button, IconSearch, IconCross, Notification } from 'hds-react';
import cx from 'classnames';
import Dropdown from './filter/Dropdown';
import { DataConfig, FilterConfig, FilterItem, FilterName, FilterType, QueryParams } from '../../../../types/common';
import TagList from './tag/TagList';
import { useTranslation } from 'react-i18next';
import useFilters from '../../../../hooks/useFilters';
import FloorCombinedFilter from './filter/FloorCombinedFilter';

const FLOOR_PROPERTY_OPTIONS = ['TOP_FLOOR', 'BOTTOM_FLOOR', 'NOT_TOP_FLOOR', 'NOT_BOTTOM_FLOOR'];
const FLOOR_RANGE_FILTER_NAME = 'floor';

const normalizePropertyOption = (value: string) => value.trim().toUpperCase();

const formatFloorRange = (values: string[]) => {
  const [min, max] = values;

  if (min && max) {
    return `${min}-${max}`;
  }

  if (min && !max) {
    return `> ${min}`;
  }

  if (!min && max) {
    return `< ${max}`;
  }

  return '';
};

type Props = {
  config: DataConfig | undefined;
  isLoading: boolean | undefined;
  isError: boolean | undefined;
  pageTitle: string;
  projectOwnershipType: string;
  focusRef: RefObject<HTMLDivElement>;
  onSubmit: () => void;
};

const SearchForm = ({ config, isLoading, isError, pageTitle, projectOwnershipType, focusRef, onSubmit }: Props) => {
  const { clearAllFilters, hasFilters, getFilters } = useFilters();
  const { t } = useTranslation();
  const { filters } = config || {};
  const { project_district_hitas, project_district_haso, room_count, living_area, price, properties } = filters || {};

  const propertyItems = (properties?.items || []).filter((item): item is string => typeof item === 'string');
  const floorOptionSet = new Set(FLOOR_PROPERTY_OPTIONS);

  const floorPropertyItems = propertyItems.filter((item) => floorOptionSet.has(normalizePropertyOption(item)));
  const additionalPropertyItems = propertyItems.filter((item) => !floorOptionSet.has(normalizePropertyOption(item)));

  const createScopedPropertiesFilter = (label: string, scopedItems: string[]) => {
    if (!properties || scopedItems.length === 0) {
      return null;
    }

    const scopedItemSet = new Set(scopedItems.map(normalizePropertyOption));

    const getLocalizedPropertyLabel = (value: string) => {
      const normalizedValue = normalizePropertyOption(value);
      const normalizedTranslation = t(`ES:${normalizedValue}`);

      if (normalizedTranslation !== `ES:${normalizedValue}`) {
        return normalizedTranslation;
      }

      return t(`ES:${value}`);
    };

    return {
      ...properties,
      label,
      items: scopedItems,
      getLabel: (values: string[]) => {
        const scopedValues = values.filter((value) => scopedItemSet.has(normalizePropertyOption(value)));
        if (scopedValues.length === 0) {
          return label;
        }

        const [firstValue] = scopedValues;
        if (!firstValue) {
          return label;
        }

        return getLocalizedPropertyLabel(firstValue) + (scopedValues.length > 1 ? ` +${scopedValues.length - 1}` : '');
      },
    };
  };

  const additionalPropertiesFilter = createScopedPropertiesFilter(properties?.label || '', additionalPropertyItems);
  const floorPropertiesFilter = createScopedPropertiesFilter(t('SEARCH:floor'), floorPropertyItems);

  const getItemLabel = (item: string | FilterItem | undefined): string => {
    if (!item) {
      return '';
    }

    return typeof item === 'string' ? item : item.label;
  };

  const livingAreaItems = (living_area?.items || []) as (string | FilterItem)[];
  const floorMinLabel = getItemLabel(livingAreaItems[0]) || 'At least';
  const floorMaxLabel = getItemLabel(livingAreaItems[1]) || 'At most';

  const floorRangeFilter: FilterConfig = {
    label: t('SEARCH:floor-range'),
    type: FilterType.Range,
    translateItems: false,
    items: [{ label: floorMinLabel }, { label: floorMaxLabel }],
    getQuery: (values: string[]) => {
      const [minRaw = '', maxRaw = ''] = values;
      const query: QueryParams = {};

      if (minRaw !== '') {
        const min = Number(minRaw);
        if (!Number.isNaN(min)) {
          query.floor_min = min;
        }
      }

      if (maxRaw !== '') {
        const max = Number(maxRaw);
        if (!Number.isNaN(max)) {
          query.floor_max = max;
        }
      }

      return query;
    },
    getLabel: (values: string[]) => formatFloorRange(values),
    getTagLabel: (serializedValue: string) => {
      const formattedRange = formatFloorRange(serializedValue.split(','));
      return [[FLOOR_RANGE_FILTER_NAME as FilterName, formattedRange]];
    },
  };

  const floorRangeValues = getFilters(FLOOR_RANGE_FILTER_NAME);
  const floorRangeLabel = formatFloorRange(floorRangeValues);

  const floorCombinedFilter = floorPropertiesFilter
    ? {
        ...floorPropertiesFilter,
        getLabel: (values: string[]) => {
          const propertyLabel = floorPropertiesFilter.getLabel(values);
          if (propertyLabel !== floorPropertiesFilter.label) {
            return propertyLabel;
          }

          return floorRangeLabel || floorPropertiesFilter.label;
        },
      }
    : null;

  const filtersWithFloor = {
    ...(filters || {}),
    [FLOOR_RANGE_FILTER_NAME]: floorRangeFilter,
  } as DataConfig['filters'];

  const searchButton = () => (
    <Button className={styles.submitButton} onClick={() => onSubmit()} iconStart={<IconSearch aria-hidden="true" />}>
      {t('SEARCH:search')}
    </Button>
  );

  if (!isLoading && isError) {
    return (
      <section className={styles.container} aria-label={t('SEARCH:aria-filters-title')}>
        <div className={styles.form}>
          <h1>{pageTitle}</h1>
          <Notification label={t('SEARCH:error')} type="error">
            {t('SEARCH:filters-error-text')}
          </Notification>
        </div>
      </section>
    );
  }

  return (
    <section
      className={`${styles.container} ${isLoading ? styles.isLoading : ''}`}
      aria-label={t('SEARCH:aria-filters-title')}
    >
      <div className={styles.form}>
        <h1>{pageTitle}</h1>
        <div className={cx(styles.row, styles.hasBottomPadding)}>
          <div className={cx(styles.column, styles.columnWide, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>
              {projectOwnershipType.toLowerCase() === 'haso'
                ? project_district_haso && <Dropdown name={FilterName.ProjectDistrict} {...project_district_haso} />
                : project_district_hitas && <Dropdown name={FilterName.ProjectDistrict} {...project_district_hitas} />}
            </div>
          </div>
          <div className={cx(styles.column, styles.columnWide, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>
              {room_count && <Dropdown name={FilterName.RoomCount} {...room_count} />}
            </div>
          </div>
          <div className={cx(styles.column, styles.columnNarrow, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>
              {living_area && <Dropdown name={FilterName.LivingArea} {...living_area} />}
            </div>
          </div>
          <div className="sr-only">
            <button type="button" onClick={() => focusRef.current?.focus()}>
              {t('SEARCH:aria-jump-to-results')}
            </button>
          </div>
        </div>
        <div className={cx(styles.row, styles.hasBottomPadding)}>
          <div className={cx(styles.column, styles.columnNarrow, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>{price && <Dropdown name={FilterName.Price} {...price} />}</div>
          </div>
          <div className={cx(styles.column, styles.columnWide, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>
              {additionalPropertiesFilter && (
                <Dropdown
                  name={FilterName.Properties}
                  selectionScopeValues={additionalPropertyItems}
                  {...additionalPropertiesFilter}
                />
              )}
            </div>
          </div>
          <div className={cx(styles.column, styles.columnWide, styles.dropdownColumn, styles.canShimmer)}>
            <div className={styles.dropdownWrapper}>
              {floorCombinedFilter && (
                <Dropdown
                  name={FilterName.Properties}
                  selectionScopeValues={floorPropertyItems}
                  additionalFilterNames={[FLOOR_RANGE_FILTER_NAME]}
                  customContent={
                    <FloorCombinedFilter
                      floorPropertyItems={floorPropertyItems}
                      floorRangeFrom={floorRangeFilter.items[0] as FilterItem}
                      floorRangeTo={floorRangeFilter.items[1] as FilterItem}
                    />
                  }
                  {...floorCombinedFilter}
                />
              )}
            </div>
          </div>
          <div className={cx(styles.column, styles.columnNarrow, styles.canShimmer, styles.searchButtonDesktop)}>
            {!isLoading && searchButton()}
          </div>
        </div>
        {filters && (
          <div className={styles.row}>
            <div className={styles.column} role="region" aria-label={t('SEARCH:aria-active-filters')}>
              <TagList filters={filtersWithFloor} />
            </div>
          </div>
        )}
        <div className={styles.searchButtonMobile}>{!isLoading && searchButton()}</div>
        <div className={cx(styles.row, styles.hasTopPadding)}>
          {filters && hasFilters(filtersWithFloor) && (
            <div className={cx(styles.column, styles.textCenterMobile)}>
              <button onClick={() => clearAllFilters(filtersWithFloor)} className={styles.clearFilters}>
                <IconCross aria-hidden="true" />
                <span>{t('SEARCH:clear-all-filters')}</span>
              </button>
            </div>
          )}
        </div>
      </div>
      {!isLoading && (
        <div className="sr-only">
          <button type="button" onClick={() => onSubmit()}>
            {t('SEARCH:search')}
          </button>
        </div>
      )}
    </section>
  );
};

export default SearchForm;
