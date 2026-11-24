import cx from 'classnames';
import { useTranslation } from 'react-i18next';
import { Button, ButtonVariant, IconMap, Tooltip } from 'hds-react';

import ProjectCard from './ProjectCard';
import { DataConfig, Project } from '../../../../../types/common';
import { calculateApartmentCount } from '../../../utils/calculateApartmentCount';

import css from './SearchResults.module.scss';

type Props = {
  config: DataConfig | undefined;
  searchResults: Project[];
  header: string;
  openMap?: () => void;
  showSubscribeButton?: boolean;
  showUpcomingSalesLink?: boolean;
  currentLang: string;
  resultCountByProjects?: boolean;
  hideApartments?: boolean;
  tooltipText?: string;
  description?: string;
};

const SearchResults = ({
  config,
  searchResults,
  header,
  openMap,
  showSubscribeButton = false,
  showUpcomingSalesLink = false,
  currentLang,
  resultCountByProjects = false,
  hideApartments = false,
  tooltipText = '',
  description = '',
}: Props) => {
  const { t } = useTranslation();

  return (
    <section className={css.container} aria-label={header}>
      <header>
        <div className={css.titleContainer}>
          <h2>
            {header}
            {tooltipText && (
              <Tooltip className={css.inlineTooltip} placement={'auto'} buttonLabel={t('SEARCH:aria-info-tooltip')}>
                {tooltipText}
              </Tooltip>
            )}
          </h2>
          <div className={css.resultsCount}>
            {t('SEARCH:total')}{' '}
            {resultCountByProjects
              ? `${searchResults.length} ${t('SEARCH:projects')}`
              : `${calculateApartmentCount(searchResults, currentLang)} ${t('SEARCH:apartments')}`}
          </div>
          {/* Subscribing to project mailing list is commented out until we get the backend implementation
          {description && <div className={css.description}>{description}</div>}
          */}
        </div>
        {openMap && (
          <div className={cx(css.headerButtonWrapper, description && css.hasDescription)}>
            <Button className={css.showButton} variant={ButtonVariant.Secondary} onClick={openMap} iconStart={<IconMap style={{ marginRight: 10 }} aria-hidden="true" />}>
              {t('SEARCH:show-on-map')}
            </Button>
          </div>
        )}
      </header>
      <ul className={css.resultWrapper}>
        {searchResults.map((x) => (
          <li key={x.id}>
            <ProjectCard
              config={config}
              project={x}
              showSubscribeButton={showSubscribeButton}
              showUpcomingSalesLink={showUpcomingSalesLink}
              currentLang={currentLang}
              hideApartments={hideApartments}
            />
          </li>
        ))}
      </ul>
    </section>
  );
};

export default SearchResults;
