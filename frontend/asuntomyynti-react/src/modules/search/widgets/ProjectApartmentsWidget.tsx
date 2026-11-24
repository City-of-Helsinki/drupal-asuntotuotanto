import { useCallback, useContext, useEffect, useState } from 'react';
import { Button, IconAlertCircle, IconSearch, LoadingSpinner } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { ElasticsearchQueryBody, FilterName, Project, QueryParams } from '../../../types/common';
import { DataContext } from '../DataContext';
import useSearchParams from '../../../hooks/useSearchParams';
import useSearchResults from '../../../hooks/useSearchResults';
import { getLanguageFilteredApartments } from '../utils/getLanguageFilteredApartments';
import { getProjectApplicationStatus } from '../utils/getApplicationStatus';
import {
  getUserApplications,
  userHasApplications,
  userHasReservedOrSoldApartment,
} from '../utils/userApplications';
import ApartmentTable from '../components/result/list/ApartmentTable';
import Dropdown from '../components/form/filter/Dropdown';
import useFilters from '../../../hooks/useFilters';

import styles from './ProjectApartmentsWidget.module.scss';

type Props = {
  projectUuid: string;
  projectOwnershipType: string;
  projectStateOfSale?: string;
};

const ProjectApartmentsWidget = ({
  projectUuid,
  projectOwnershipType,
  projectStateOfSale,
}: Props) => {
  const { t, i18n } = useTranslation();
  const searchQuery = useSearchParams();
  const currentLang = searchQuery.get('lang') || 'fi';

  useEffect(() => {
    i18n.changeLanguage(currentLang);
  }, [currentLang, i18n]);

  const { data: config, isLoading: isConfigLoading, isError: isConfigError } =
    useContext(DataContext) || {};

  const { getFilters } = useFilters();
  const ownershipForApi = projectOwnershipType.trim().toLowerCase() || 'hitas';
  const [query, setQuery] = useState<ElasticsearchQueryBody>(() => ({
    project_uuid: projectUuid,
    project_ownership_type: ownershipForApi,
    ...(projectStateOfSale ? { project_state_of_sale: [projectStateOfSale] } : {}),
  }));

  const buildFilterQuery = useCallback((): QueryParams => {
    const { filters } = config || {};
    if (!filters) {
      return {};
    }

    const filterNames = [FilterName.RoomCount, FilterName.LivingArea, FilterName.Price];

    return filterNames.reduce((allFilters: QueryParams, name) => {
      const values = getFilters(name);
      const { getQuery } = filters[name] || {};

      if (values.length === 0 || !getQuery) {
        return allFilters;
      }

      allFilters[name] = values;
      return { ...allFilters, ...getQuery(values) };
    }, {});
  }, [config, getFilters]);

  const updateQuery = useCallback(() => {
    const ownershipType = projectOwnershipType.trim().toLowerCase() || 'hitas';
    const localQuery: ElasticsearchQueryBody = {
      project_uuid: projectUuid,
      project_ownership_type: ownershipType,
      ...buildFilterQuery(),
    };
    if (projectStateOfSale) {
      localQuery.project_state_of_sale = [projectStateOfSale];
    }
    setQuery(localQuery);
  }, [buildFilterQuery, projectOwnershipType, projectStateOfSale, projectUuid]);

  useEffect(() => {
    if (config) {
      updateQuery();
    }
  }, [config, updateQuery]);

  const queryHeaders = { token: config?.token };
  const {
    data: searchResults,
    isFetching: isSearchQueryFetching,
    isError: isSearchQueryError,
  } = useSearchResults(query, queryHeaders, currentLang);

  const project = (searchResults as Project[]).find(
    (item: Project) => item.uuid === projectUuid
  );

  const hasError = isConfigError || isSearchQueryError;
  const isLoading = !config || isConfigLoading || isSearchQueryFetching;

  const filteredApartments = project
    ? getLanguageFilteredApartments(project.apartments, currentLang)
    : [];

  const { filters } = config || {};
  const { room_count, living_area, price } = filters || {};
  const showFilterPlaceholders = !filters;

  return (
    <section className={styles.container} aria-label={t('SEARCH:apartments')}>
      <div className={styles.headerWrapper}>
        <header className={styles.header}>
          <h2>{t('SEARCH:project-apartments')}</h2>
          <h3 className={styles.resultsCount}>
            {t('SEARCH:total')} {filteredApartments.length} {t('SEARCH:apartments')}
          </h3>
        </header>
      </div>

      <div className={styles.content}>
        <form
          className={styles.filterBar}
          onSubmit={(event) => {
            event.preventDefault();
            updateQuery();
          }}
        >
          {showFilterPlaceholders ? (
            <>
              <div className={styles.filterPlaceholder} aria-hidden="true" />
              <div className={styles.filterPlaceholder} aria-hidden="true" />
              <div className={styles.filterPlaceholder} aria-hidden="true" />
              <div className={styles.filterItemSubmit}>
                <Button type="submit" iconStart={<IconSearch aria-hidden="true" />} disabled>
                  {t('SEARCH:search')}
                </Button>
              </div>
            </>
          ) : (
            <>
              <div className={styles.filterItem}>
                {room_count && <Dropdown name={FilterName.RoomCount} {...room_count} />}
              </div>
              <div className={styles.filterItem}>
                {living_area && <Dropdown name={FilterName.LivingArea} {...living_area} />}
              </div>
              <div className={styles.filterItem}>
                {price && <Dropdown name={FilterName.Price} {...price} />}
              </div>
              <div className={styles.filterItemSubmit}>
                <Button type="submit" iconStart={<IconSearch aria-hidden="true" />}>
                  {t('SEARCH:search')}
                </Button>
              </div>
            </>
          )}
        </form>

        {hasError ? (
          <div className={styles.errorToastWrapper}>
            <IconAlertCircle className={styles.alertIcon} />
            {t('SEARCH:error-while-loading-results')}
          </div>
        ) : isLoading ? (
          <div className={styles.loadingSpinnerWrapper}>
            <LoadingSpinner />
          </div>
        ) : project ? (
          <ApartmentTable
            apartments={filteredApartments}
            applications={getUserApplications(config?.user, project.id)}
            applicationStatus={getProjectApplicationStatus(
              config?.apartment_application_status,
              project.id
            )}
            userHasApplicationForProject={userHasApplications(config?.user, project.id)}
            userHasReservedOrSoldApartmentInProject={userHasReservedOrSoldApartment(
              config,
              project.id
            )}
            housingCompany={project.housing_company}
            projectID={project.id}
            projectOwnershipIsHaso={projectOwnershipType.toLowerCase() === 'haso'}
          />
        ) : (
          <div className={styles.noResults}>
            <IconAlertCircle className={styles.alertIcon} />
            {t('SEARCH:no-results')}
          </div>
        )}
      </div>
    </section>
  );
};

export default ProjectApartmentsWidget;
