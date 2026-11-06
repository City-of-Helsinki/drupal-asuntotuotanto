import { useEffect, useState } from 'react';
import cx from 'classnames';
import { useTranslation } from 'react-i18next';
import { IconAngleDown, IconAngleUp, IconPenLine, IconSize } from 'hds-react';

import { Apartment, ApartmentStateOfSale, ApplicationStatus } from '../../../../../types/common';
import { fullURL } from '../../../utils/fullURL';
import { withApartmentParam } from '../../../utils/withApartmentParam';
import { computeApplicationFlags } from '../../../utils/applicationFlags';
import { getApartmentPrice } from '../../../utils/getApartmentPrice';
import { userHasApplicationForApartment } from '../../../utils/userApplications';
import RenderAvailabilityInfo from '../ApplicationStatus';
import useSessionStorageState from '../../../../../hooks/useSessionStorageState';
import formattedLivingArea from '../../../utils/formatLivingArea';

import css from './ApartmentRow.module.scss';
import CreateApplicationButton from './CreateApplicationButton';
import ContactUsButton from './ContactUsButton';
import GetToKnowButton from './GetToKnowButton';

const BREAK_POINT = 768;

type Props = {
  apartment: Apartment;
  userApplications: number[] | undefined;
  applicationStatus: string | undefined;
  userHasApplicationForProject: boolean;
  userHasReservedOrSoldApartmentInProject: boolean;
  projectOwnershipIsHaso: boolean;
};

const ApartmentRow = ({
  apartment,
  userApplications,
  applicationStatus,
  userHasApplicationForProject: _userHasApplicationForProject,
  userHasReservedOrSoldApartmentInProject,
  projectOwnershipIsHaso,
}: Props) => {
  const { apartment_number, apartment_state_of_sale, apartment_structure, floor, floor_max, nid, living_area, url } =
    apartment;

  const { t } = useTranslation();
  const [width, setWidth] = useState(window.innerWidth);
  const [rowOpen, setRowOpen] = useSessionStorageState({ defaultValue: false, key: `apartmentRowOpen-${nid}` });
  const isDesktopSize = width > BREAK_POINT;
  const isMobileSize = width <= BREAK_POINT;

  const handleResize = () => {
    setWidth(window.innerWidth);
  };

  const toggleRow = () => {
    if (isMobileSize) {
      setRowOpen(!rowOpen);
    }
  };

  useEffect(() => {
    window.addEventListener('resize', handleResize);

    return () => window.removeEventListener('resize', handleResize);
  }, []);

  const {
    isApartmentFree,
    isApplicationPeriodActive,
    canApplyAfterwards,
    canMakeReservation,
    ctaVariant,
    contactUrl,
    applicationUrl,
  } = computeApplicationFlags(apartment, userHasReservedOrSoldApartmentInProject, projectOwnershipIsHaso);

  const isApartmentReserved =
    apartment_state_of_sale === ApartmentStateOfSale.RESERVED.valueOf() ||
    apartment_state_of_sale === ApartmentStateOfSale.RESERVED_HASO.valueOf();
  const isApartmentSold = apartment_state_of_sale === ApartmentStateOfSale.SOLD.valueOf();
  const isApartmentReservedByApplicationStatus =
    applicationStatus === ApplicationStatus.Reserved ||
    applicationStatus === ApplicationStatus.ReservedHaso;
  const isApartmentSoldByApplicationStatus = applicationStatus === ApplicationStatus.Sold;
  const isApartmentReservedOrSold =
    isApartmentReserved ||
    isApartmentSold ||
    isApartmentReservedByApplicationStatus ||
    isApartmentSoldByApplicationStatus;

  let reservedOrSoldLabel = '';
  if (isApartmentSold || isApartmentSoldByApplicationStatus) {
    reservedOrSoldLabel = t('SEARCH:apartment-sold');
  } else if (isApartmentReserved || isApartmentReservedByApplicationStatus) {
    reservedOrSoldLabel = t('SEARCH:apartment-reserved');
  } else if (!isApplicationPeriodActive && isApartmentFree) {
    // Outside application period, show "free" instead of application count.
    reservedOrSoldLabel = t('SEARCH:apartment-free');
  }

  let statusForDot: string;
  if (isApartmentReserved || isApartmentReservedByApplicationStatus) {
    statusForDot = ApplicationStatus.Reserved;
  } else if (isApartmentSold || isApartmentSoldByApplicationStatus) {
    statusForDot = ApplicationStatus.Sold;
  } else {
    statusForDot = applicationStatus || ApplicationStatus.Low;
  }

  const applicationHref = fullURL(withApartmentParam(applicationUrl, nid));

  const apartmentRowBaseDetails = (
    <>
      <strong>
        <span className="sr-only">{t('SEARCH:apartment')}: </span>
        {apartment_number}
      </strong>
      {isMobileSize ? (
        <>
          <span className={css.apartmentAvailabilityMobile}>
            <RenderAvailabilityInfo
              status={statusForDot}
              dotOnly={true}
              labelOverride={reservedOrSoldLabel || undefined}
            />
          </span>
          <span>
            <span className="sr-only">{t('SEARCH:aria-apartment-structure')}: </span>
            {apartment_structure}
          </span>
          {rowOpen ? (
            <IconAngleUp style={{ marginLeft: 'auto' }} size={IconSize.Small} aria-hidden="true" />
          ) : (
            <IconAngleDown style={{ marginLeft: 'auto' }} size={IconSize.Small} aria-hidden="true" />
          )}
        </>
      ) : (
        <span>
          <span className="sr-only">{t('SEARCH:aria-apartment-structure')}: </span>
          {apartment_structure}
        </span>
      )}
    </>
  );

  const apartMentRowOtherDetails = (
    <>
      <div className={css.cell}>
        <span className={isDesktopSize ? 'sr-only' : css.cellMobileTitle}>{t('SEARCH:floor')}&nbsp; </span>
        <span>
          {floor} {!!floor_max && floor_max > 1 && ` / ${floor_max}`}
        </span>
      </div>
      <div className={css.cell}>
        <span className={isDesktopSize ? 'sr-only' : css.cellMobileTitle}>{t('SEARCH:area')}&nbsp; </span>
        <span>{formattedLivingArea(living_area)}</span>
      </div>
      <div className={css.cell}>
        <span className={isDesktopSize ? 'sr-only' : css.cellMobileTitle}>
          {projectOwnershipIsHaso ? t('SEARCH:right-of-occupancy-payment') : t('SEARCH:free-of-debt-price')}&nbsp;{' '}
        </span>
        <span>{getApartmentPrice(apartment)}</span>
      </div>
      <div className={css.cell}>
        <span className={isDesktopSize ? 'sr-only' : css.cellMobileTitle}>{t('SEARCH:applications')}&nbsp; </span>
        <span>
          <RenderAvailabilityInfo status={statusForDot} labelOverride={reservedOrSoldLabel || undefined} />
        </span>
      </div>
    </>
  );

  const apartmentRowActions = (
    <div className={css.cellButtons}>
      {userHasApplicationForApartment(userApplications, nid) ? (
        <div className={css.verticalContent}>
          <div className={css.applicationSent}>
            <IconPenLine style={{ marginRight: 10 }} aria-hidden="true" />
            <span>{t('SEARCH:user-application-apartment')}</span>
          </div>
          {url && (
            <a
              href={fullURL(url)}
              className={`${css.openApartmentDetailsButton} hds-button hds-button--${
                isDesktopSize ? 'secondary' : 'primary'
              } hds-button--small`}
            >
              <span className="hds-button__label">
                {t('SEARCH:open-apartment-page')}
                <span className="sr-only">
                  , {t('SEARCH:apartment')} {apartment_number}
                </span>
              </span>
            </a>
          )}
        </div>
      ) : (
        <div className={css.buttons}>
          {url && <GetToKnowButton href={fullURL(url)} apartment={apartment} isDesktopSize={isDesktopSize} />}

          {ctaVariant && <CreateApplicationButton href={applicationHref} apartment={apartment} variant={ctaVariant} />}

          {isApartmentFree && !canApplyAfterwards && !canMakeReservation && !isApartmentReservedOrSold && (
            <ContactUsButton href={fullURL(contactUrl)} apartment={apartment} isDesktopSize={isDesktopSize} />
          )}
        </div>
      )}
    </div>
  );

  return (
    <li className={css.apartmentTableRow}>
      {isMobileSize ? (
        <>
          <button
            className={css.apartmentCellMobile}
            onClick={toggleRow}
            aria-controls={`apartment-row-details-${nid}`}
            aria-expanded={rowOpen ? true : false}
          >
            {apartmentRowBaseDetails}
          </button>
          <div
            className={rowOpen ? cx(css.mobileCells, css.open) : css.mobileCells}
            id={`apartment-row-details-${nid}`}
          >
            {apartMentRowOtherDetails}
            {apartmentRowActions}
          </div>
        </>
      ) : (
        <>
          <div className={cx(css.cell, css.apartmentCell)}>{apartmentRowBaseDetails}</div>
          {apartMentRowOtherDetails}
          {apartmentRowActions}
        </>
      )}
    </li>
  );
};

export default ApartmentRow;
