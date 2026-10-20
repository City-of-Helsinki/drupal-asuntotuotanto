import { useTranslation } from 'react-i18next';
import cx from 'classnames';

import { ApplicationStatus } from '../../../../types/common';
import css from './ApplicationStatus.module.scss';

type Props = {
  status: string | undefined;
  dotOnly?: boolean;
  labelOverride?: string;
};

const RenderAvailabilityInfo = ({ status, dotOnly = false, labelOverride }: Props) => {
  const { t } = useTranslation();

  switch (status) {
    case ApplicationStatus.Vacant:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleFree)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-free')}
          </span>
        </>
      );
    case ApplicationStatus.ReservedHaso:
    case ApplicationStatus.Reserved:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleReserved)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-reserved')}
          </span>
        </>
      );
    case ApplicationStatus.Sold:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleSold)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-sold')}
          </span>
        </>
      );
    case ApplicationStatus.Low:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleFew)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-few-applications')}
          </span>
        </>
      );
    case ApplicationStatus.Medium:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleSome)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-some-applications')}
          </span>
        </>
      );
    case ApplicationStatus.High:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleLots)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-lots-of-applications')}
          </span>
        </>
      );
    default:
      return (
        <>
          <span className={cx(css.statusCircle, css.statusCircleNone)} aria-hidden="true" />
          <span className={dotOnly ? 'sr-only' : ''}>
            {labelOverride ?? t('SEARCH:apartment-no-applications')}
          </span>
        </>
      );
  }
};

export default RenderAvailabilityInfo;
