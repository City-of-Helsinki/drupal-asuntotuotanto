import { IconArrowDown, IconCross, Button, ButtonVariant } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { DataConfig, Project } from '../../../../../types/common';
import { getLanguageFilteredApartments } from '../../../utils/getLanguageFilteredApartments';
import { fullURL } from '../../../utils/fullURL';
import Label from '../../../../../common/label/Label';
// import SubscribeToProjectMailinglist from '../SubscribeToProjectMailinglist';

import css from './MapProjectPopupCard.module.scss';

type Props = {
  config: DataConfig | undefined;
  project: Project;
  currentLang: string;
  hideApartments?: boolean;
  activeProject: Project | null;
  showSubscribeButton?: boolean;
  onCloseBtnClick: () => void;
  onApartmentsBtnClick: () => void;
};

const MapProjectPopupCard = ({
  project,
  currentLang,
  hideApartments = false,
  activeProject,
  onCloseBtnClick,
  onApartmentsBtnClick,
}: Props) => {
  const { t } = useTranslation();
  const { apartments, id, housing_company, main_image_url, ownership_type, url, state_of_sale } = project;
  const filteredApartments = getLanguageFilteredApartments(apartments, currentLang);
  const hasApartments = !!filteredApartments.length;

  return (
    <>
      <div className={css.mapProjectDetailImage} aria-hidden="true">
        {main_image_url && <img src={fullURL(main_image_url)} alt="" />}
      </div>
      <button
        className={css.closeIcon}
        onClick={() => onCloseBtnClick()}
        aria-label={t('SEARCH:aria-hide-project-popup')}
      >
        <IconCross aria-hidden="true" />
      </button>
      <div className={css.labelWrap}>
        <Label type={ownership_type}>
          <>
            <span className="sr-only">{t('SEARCH:aria-ownership-type')}</span> {ownership_type}
          </>
        </Label>
      </div>
      <div className={css.projectDetails}>
        <span className="sr-only">{t('SEARCH:aria-state-of-sale')}, </span>
        {t(`ES:${state_of_sale}`)}
        <h3>{housing_company}</h3>
      </div>

      <div className={css.buttons}>
        {url && (
          <a
            href={fullURL(url)}
            className={`${css.getToKnowButton} hds-button hds-button--secondary hds-button--small`}
          >
            {t('SEARCH:learn-more')}
            <span className="sr-only">, {housing_company}</span>
          </a>
        )}
        {/* Subscribing to project mailing list is commented out until we get the backend implementation */}
        {/* {showSubscribeButton && config && (
          <div className={css.subscribeButtonWrapper}>
            <SubscribeToProjectMailinglist project={project} config={config} />
          </div>
        )} */}
        {!hideApartments && hasApartments && (
          <Button
            className={css.apartmentListButton}
            onClick={() => onApartmentsBtnClick()}
            variant={ButtonVariant.Supplementary}
            iconEnd={<IconArrowDown aria-hidden="true" />}
            aria-haspopup="true"
            aria-controls={`map-project-popup-${id}`}
            aria-expanded={activeProject?.id === id ? true : false}
            aria-label={`${t('SEARCH:show-all')} ${filteredApartments.length} ${t('SEARCH:apartments')}`}
          >
            {`${filteredApartments.length} ${t('SEARCH:apartments')}`}
          </Button>
        )}
      </div>
    </>
  );
};

export default MapProjectPopupCard;
