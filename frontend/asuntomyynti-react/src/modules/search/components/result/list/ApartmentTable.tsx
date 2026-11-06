import { useEffect, useRef, useState } from 'react';
import cx from 'classnames';
import { IconAngleLeft, IconAngleRight, IconSortAscending, IconSortDescending } from 'hds-react';
import { useTranslation } from 'react-i18next';

import { Apartment, ApartmentApplicationStatusConfig, Project } from '../../../../../types/common';
import { getApartmentApplicationStatus } from '../../../utils/getApplicationStatus';
import SortApartments from '../../../utils/sortApartments';
import ApartmentRow from './ApartmentRow';
import useSessionStorageState from '../../../../../hooks/useSessionStorageState';

import css from './ApartmentTable.module.scss';

type Props = {
  apartments: Apartment[];
  applications: number[] | undefined;
  applicationStatus: ApartmentApplicationStatusConfig | undefined;
  housingCompany: string | undefined;
  projectID: Project['id'];
  projectOwnershipIsHaso: boolean;
  userHasApplicationForProject: boolean;
  userHasReservedOrSoldApartmentInProject: boolean;
};

const ApartmentTable = ({
  apartments,
  applications,
  applicationStatus,
  housingCompany,
  projectID,
  projectOwnershipIsHaso,
  userHasApplicationForProject,
  userHasReservedOrSoldApartmentInProject,
}: Props) => {
  const { t } = useTranslation();
  const [page, setPage] = useSessionStorageState({ defaultValue: 1, key: `apartmentTablePaginationPage-${projectID}` });
  const [staleApartments, setStaleapartments] = useState(apartments);
  const paginationScrollRef = useRef<HTMLDivElement>(null);
  const { items, requestSort, sortConfig } = SortApartments(apartments, `project-${projectID}`);
  const displayedApartments = items.slice(page * 10 - 10, page * 10);
  const showPagination = items.length > 10;
  const noOfPages = Math.ceil(items.length / 10);

  useEffect(() => {
    // when apartments change, always jump to page 1
    if (staleApartments === apartments) {
      setPage(1);
      setStaleapartments(apartments);
    }
  }, [setPage, staleApartments, apartments]);

  const setSort = (key: string, alphaNumeric: boolean) => {
    requestSort(key, alphaNumeric);
    setPage(1);
  };

  const isCurrentlyActiveSort = (key: string) => {
    return sortConfig ? sortConfig.key === key : false;
  };

  const getSortDirectionFor = (name: string) => {
    if (!sortConfig) {
      return;
    }
    return sortConfig.key === name ? sortConfig.direction : undefined;
  };

  const apartmentSortClasses = (key: string) => {
    return cx(css.sortButton, {
      [css.activeSort]: isCurrentlyActiveSort(key),
      [css.ascending]: getSortDirectionFor(key) === 'ascending',
      [css.descending]: getSortDirectionFor(key) === 'descending',
    });
  };

  const getSortIcon = (key: string) => {
    if (!sortConfig) {
      return;
    }
    if (getSortDirectionFor(key) === 'descending') {
      return <IconSortDescending aria-label={t('SEARCH:aria-descending')} className={css.sortArrow} />;
    }
    return <IconSortAscending aria-label={t('SEARCH:aria-ascending')} className={css.sortArrow} />;
  };

  const renderPaginationButtons = () => {
    const buttons = [];

    buttons.push(
      <button
        key={'pagination-btn-prev'}
        className={css.paginationButton}
        onClick={() => handlePageClick(page !== 1 ? page - 1 : page)}
        value={page !== 1 ? page - 1 : page}
        aria-label={t('SEARCH:previous-page')}
        disabled={page === 1}
      >
        <IconAngleLeft aria-hidden="true" />
      </button>
    );

    for (let i = 1; i <= noOfPages; i++) {
      buttons.push(
        <button
          key={i}
          className={cx(css.paginationButton, i === page && css.active)}
          onClick={() => handlePageClick(i)}
          value={i}
          aria-current={i === page ? true : false}
        >
          <span className="sr-only">{i === page && <>({t('SEARCH:aria-is-current-page')})</>}&nbsp;</span>
          <span className="sr-only">{t('SEARCH:aria-page')}&nbsp;</span>
          {i}
          <span className="sr-only">/{noOfPages}</span>
        </button>
      );
    }

    buttons.push(
      <button
        key={'pagination-btn-next'}
        className={css.paginationButton}
        onClick={() => handlePageClick(page !== noOfPages ? page + 1 : page)}
        value={page !== noOfPages ? page + 1 : page}
        aria-label={t('SEARCH:next-page')}
        disabled={page === noOfPages}
      >
        <IconAngleRight aria-hidden="true" />
      </button>
    );

    return buttons;
  };

  const paginationScroll = () => {
    if (paginationScrollRef.current) {
      paginationScrollRef.current.focus();
    }
  };

  const handlePageClick = (index: number) => {
    if (index !== page) {
      paginationScroll();
      setPage(index);
    }
  };

  return (
    <div
      className={css.apartmentList}
      ref={paginationScrollRef}
      tabIndex={-1}
      aria-label={`${t('SEARCH:aria-apartment-list-for-project')}: ${housingCompany}, ${
        showPagination && t('SEARCH:aria-page') + page + '/' + noOfPages
      }`}
      role="group"
    >
      <ul className={css.apartmentListTable}>
        <li className={css.apartmentListHeaders} aria-label={t('SEARCH:aria-sort-apartments')}>
          <div className={cx(css.headerCell, css.headerCellSortable, css.headerCellApartment)}>
            <button
              type="button"
              onClick={() => setSort('apartment_number', true)}
              className={apartmentSortClasses('apartment_number')}
            >
              <span className="sr-only">
                {isCurrentlyActiveSort('apartment_number')
                  ? t('SEARCH:aria-is-active-sort')
                  : t('SEARCH:aria-set-sort')}
                ,&nbsp;
              </span>
              <span>{t('SEARCH:apartment')}</span>
              {getSortIcon('apartment_number')}
            </button>
          </div>
          <div className={cx(css.headerCell, css.headerCellSortable, css.headerCellNarrow)}>
            <button type="button" onClick={() => setSort('floor', false)} className={apartmentSortClasses('floor')}>
              <span className="sr-only">
                {isCurrentlyActiveSort('floor') ? t('SEARCH:aria-is-active-sort') : t('SEARCH:aria-set-sort')},&nbsp;
              </span>
              <span>{t('SEARCH:floor')}</span>
              {getSortIcon('floor')}
            </button>
          </div>
          <div className={cx(css.headerCell, css.headerCellSortable, css.headerCellNarrow)}>
            <button
              type="button"
              onClick={() => setSort('living_area', false)}
              className={apartmentSortClasses('living_area')}
            >
              <span className="sr-only">
                {isCurrentlyActiveSort('living_area') ? t('SEARCH:aria-is-active-sort') : t('SEARCH:aria-set-sort')}
                ,&nbsp;
              </span>
              <span>{t('SEARCH:area')}</span>
              {getSortIcon('living_area')}
            </button>
          </div>
          <div className={cx(css.headerCell, css.headerCellSortable, css.headerCellNarrow)}>
            {projectOwnershipIsHaso ? (
              <button
                type="button"
                onClick={() => setSort('right_of_occupancy_payment', false)}
                className={apartmentSortClasses('right_of_occupancy_payment')}
              >
                <span className="sr-only">
                  {isCurrentlyActiveSort('right_of_occupancy_payment')
                    ? t('SEARCH:aria-is-active-sort')
                    : t('SEARCH:aria-set-sort')}
                  ,&nbsp;
                </span>
                <span>{t('SEARCH:right-of-occupancy-payment')}</span>
                {getSortIcon('right_of_occupancy_payment')}
              </button>
            ) : (
              <button
                type="button"
                onClick={() => setSort('debt_free_sales_price', false)}
                className={apartmentSortClasses('debt_free_sales_price')}
              >
                <span className="sr-only">
                  {isCurrentlyActiveSort('debt_free_sales_price')
                    ? t('SEARCH:aria-is-active-sort')
                    : t('SEARCH:aria-set-sort')}
                  ,&nbsp;
                </span>
                <span>{t('SEARCH:free-of-debt-price')}</span>
                {getSortIcon('debt_free_sales_price')}
              </button>
            )}
          </div>
          <div className={cx(css.headerCell, css.headerCellNarrow, css.headerCellSpan)} aria-hidden="true">
            <span>{t('SEARCH:applications')}</span>
          </div>
          <div className={cx(css.headerCell, css.headerCellSpacer)} />
        </li>
        {displayedApartments.map((x) => (
          <ApartmentRow
            key={x.uuid}
            apartment={x}
            userApplications={applications}
            applicationStatus={getApartmentApplicationStatus(applicationStatus, x.nid)}
            userHasApplicationForProject={userHasApplicationForProject}
            userHasReservedOrSoldApartmentInProject={userHasReservedOrSoldApartmentInProject}
            projectOwnershipIsHaso={projectOwnershipIsHaso}
          />
        ))}
      </ul>
      {showPagination && (
        <div
          role="navigation"
          aria-label={`${t('SEARCH:aria-pagination')}, ${housingCompany}`}
          className={css.pagination}
        >
          {renderPaginationButtons()}
        </div>
      )}
    </div>
  );
};

export default ApartmentTable;
