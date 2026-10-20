import { IconAlertCircle, LoadingSpinner } from 'hds-react';
import { useContext, useEffect, useRef, useState } from 'react';
import { useTranslation } from 'react-i18next';

import ErrorToast from '../../common/errorToast/ErrorToast';
import InfoBlock from '../../common/infoblock/InfoBlock';
import useSearchParams from '../../hooks/useSearchParams';
import useSearchQuery from '../../hooks/useSearchQuery';
import useSearchResults from '../../hooks/useSearchResults';
import useSessionStorageState from '../../hooks/useSessionStorageState';
import SearchForm from './components/form/SearchForm';
import SearchResults from './components/result/list/SearchResults';
import MapContainer from './components/result/map/MapResults';
import { DataContext } from './DataContext';
import { filterProjectsByOwnershipType } from './utils/filterProjectsByOwnershipType';
import { groupProjectsByState } from './utils/groupProjectsByState';

import styles from './SearchContainer.module.scss';

// "boolean" as a string because env variables are also treated as strings
const showUpcomingOnly = import.meta.env.VITE_SHOW_UPCOMING_ONLY || 'false';

// Read project_ownership_type from env variables, defaults to hitas (includes puolihitas)
const projectOwnershipType = import.meta.env.VITE_PROJECT_OWNERSHIP_TYPE || 'hitas';

const SearchContainer = () => {
  const [showMap, setShowMap] = useSessionStorageState({ defaultValue: false, key: 'showMap' });
  const [hasSubmitted, setHasSubmitted] = useState(false);
  const [announce, setAnnounce] = useState(false);
  const mapFocusRef = useRef<HTMLDivElement>(null);
  const { t, i18n } = useTranslation();

  // Get current language from url parameters
  const searchQuery = useSearchParams();
  const currentLang = searchQuery.get('lang') || 'fi';

  // Set language
  useEffect(() => {
    i18n.changeLanguage(currentLang);
  }, [currentLang, i18n]);

  // Get Config data from the initialize API
  const { data: config, isLoading: isConfigLoading, isError: isConfigError } = useContext(DataContext) || {};

  // Query, as in elasticsearch query params
  const { query, updateQuery } = useSearchQuery(projectOwnershipType, showUpcomingOnly);

  // Set query headers
  const queryHeaders = { token: config?.token };

  // Fetch results with current search query
  const { data: searchResults, isFetching: isSearchQueryFetching, isError: isSearchQueryError } = useSearchResults(
    query,
    queryHeaders,
    currentLang
  );

  // Filter HITAS/HASO apartments by selected ownership type
  const filteredSearchResults = filterProjectsByOwnershipType(searchResults ?? [], projectOwnershipType);

  // Set READY, FOR_SALE, PRE_MARKETING and UPCOMING apartments from HITAS/HASO filtered lists
  const {
    READY: ready = [],
    FOR_SALE: forSale = [],
    PRE_MARKETING: preMarketing = [],
    PROCESSING: processing = [],
    UPCOMING: upcoming = [],
  } = groupProjectsByState(filteredSearchResults);

  const forSaleAndProcessing = forSale.concat(processing);
  const searchResultsWithoutUpcoming = ready.concat(forSaleAndProcessing, preMarketing);

  const hasFreeApartments = !!ready.length;

  // Define page h1 based on project_ownership_type
  const pageTitle = projectOwnershipType.toLowerCase() === 'haso' ? t('SEARCH:haso-title') : t('SEARCH:hitas-title');

  const openMap = () => {
    setShowMap(true);
    mapFocusRef.current?.focus();
  };

  const closeMap = () => {
    setShowMap(false);
    mapFocusRef.current?.focus();
  };

  const submitQuery = () => {
    updateQuery();
    // When the first time submit button is clicked, set this to true
    setHasSubmitted(true);
    // Clear old screen reader announcement messages
    setAnnounce(false);
  };

  useEffect(() => {
    if (hasSubmitted) {
      // Show screen reader announcement for search results
      setAnnounce(true);
      setTimeout(() => {
        // Clear screen reader announcements after 5 seconds
        setAnnounce(false);
      }, 5000);
    }
  }, [hasSubmitted, query]);

  const spinner = () => (
    <div className={styles.loadingSpinnerWrapper}>
      <LoadingSpinner />
    </div>
  );

  const errorMessage = () => (
    <div className={styles.errorToastWrapper}>
      <ErrorToast autoClose={false} showInline errorMessage={t('SEARCH:error-while-loading-results')} />
    </div>
  );

  const renderUpcomingMapResults = () => (
    <section aria-label={t('SEARCH:aria-label-map-results')}>
      <MapContainer
        config={config}
        header={t('SEARCH:upcoming')}
        searchResults={upcoming}
        closeMap={closeMap}
        showSubscribeButton
        currentLang={currentLang}
        resultCountByProjects
        hideApartments
        description={t('SEARCH:upcoming-projects-description')}
      />
    </section>
  );

  const renderUpcomingListResults = () => (
    <section aria-label={t('SEARCH:aria-label-list-results')}>
      <SearchResults
        config={config}
        header={t('SEARCH:upcoming')}
        searchResults={upcoming}
        openMap={openMap}
        showSubscribeButton
        currentLang={currentLang}
        resultCountByProjects
        hideApartments
        description={t('SEARCH:upcoming-projects-description')}
      />
    </section>
  );

  const renderMapResults = () => (
    <section aria-label={t('SEARCH:aria-label-map-results')}>
      <MapContainer
        config={config}
        header={t('SEARCH:all-apartments')}
        searchResults={searchResultsWithoutUpcoming}
        closeMap={closeMap}
        currentLang={currentLang}
        tooltipText={
          projectOwnershipType.toLowerCase() === 'haso'
            ? t('SEARCH:haso-all-apartments-tooltip')
            : t('SEARCH:hitas-all-apartments-tooltip')
        }
      />
    </section>
  );

  const renderFreeApartmentListResults = () => {
    if (!hasFreeApartments) {
      return null;
    }

    return (
      <SearchResults
        config={config}
        header={t('SEARCH:free-apartments')}
        searchResults={ready}
        openMap={openMap}
        currentLang={currentLang}
        tooltipText={projectOwnershipType.toLowerCase() === 'haso' ? '' : t('SEARCH:hitas-free-apartments-tooltip')}
      />
    );
  };

  const renderForSaleAndProcessingListResults = () => (
    <SearchResults
      config={config}
      header={t('SEARCH:for-sale')}
      searchResults={forSaleAndProcessing}
      openMap={hasFreeApartments ? undefined : openMap}
      currentLang={currentLang}
      tooltipText={
        projectOwnershipType.toLowerCase() === 'haso'
          ? t('SEARCH:haso-for-sale-apartments-tooltip')
          : t('SEARCH:hitas-for-sale-apartments-tooltip')
      }
    />
  );
  const renderPreMarketingListResults = () => (
    <SearchResults
      config={config}
      header={t('SEARCH:pre-marketing')}
      searchResults={preMarketing}
      currentLang={currentLang}
      showUpcomingSalesLink
      tooltipText={
        projectOwnershipType.toLowerCase() === 'haso'
          ? t('SEARCH:haso-pre-marketing-apartments-tooltip')
          : t('SEARCH:hitas-pre-marketing-apartments-tooltip')
      }
    />
  );

  const renderListResults = () => {
    if (!searchResultsWithoutUpcoming.length) {
      return (
        <section className={styles.container} aria-label={t('SEARCH:aria-label-list-results')}>
          <header>
            <h2 className={styles.noResultsText}>
              <IconAlertCircle className={styles.alertIcon} />
              {t('SEARCH:no-results')}
            </h2>
            <p>{t('SEARCH:no-results-help-text')}</p>
          </header>
        </section>
      );
    }

    return (
      <section aria-label={t('SEARCH:aria-label-list-results')}>
        {renderFreeApartmentListResults()}
        {renderForSaleAndProcessingListResults()}
        {renderPreMarketingListResults()}
      </section>
    );
  };

  return (
    <div>
      {showUpcomingOnly === 'false' && ( // Hide search filters from upcoming projects
        <>
          <SearchForm
            config={config}
            isLoading={isConfigLoading}
            isError={isConfigError}
            pageTitle={pageTitle}
            onSubmit={submitQuery}
            projectOwnershipType={projectOwnershipType}
            focusRef={mapFocusRef}
          />

          {announce && ( // Announce search changes to screen reader
            <div className="sr-only" role="status" aria-live="polite" aria-atomic="true">
              {filteredSearchResults.length === 1
                ? `${t('SEARCH:aria-search-complete')}, ${filteredSearchResults.length} ${t(
                    'SEARCH:aria-projects-result-count'
                  )}`
                : `${t('SEARCH:aria-search-complete')}, ${filteredSearchResults.length} ${t(
                    'SEARCH:aria-projects-result-count-plural'
                  )}`}
            </div>
          )}

          {!isConfigError && <InfoBlock config={config} type={projectOwnershipType} />}
        </>
      )}

      {!config ? ( // See if the config exists
        isConfigError ? (
          errorMessage() // show error message if config has error
        ) : (
          spinner() // Show the spinner while loading the config
        )
      ) : // Config exists
      isSearchQueryFetching ? (
        spinner() // show a spinner while fetching the projects
      ) : isSearchQueryError ? (
        errorMessage() // show error message if search query has error
      ) : (
        // Show results if there's no error from the search query
        <>
          <div ref={mapFocusRef} tabIndex={-1} />
          {showUpcomingOnly === 'true'
            ? showMap
              ? renderUpcomingMapResults()
              : renderUpcomingListResults()
            : showMap
            ? renderMapResults()
            : renderListResults()}
        </>
      )}
    </div>
  );
};

export default SearchContainer;
