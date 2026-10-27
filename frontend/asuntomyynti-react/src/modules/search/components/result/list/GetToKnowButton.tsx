import { useTranslation } from 'react-i18next';
import { Apartment } from "../../../../../types/common";
import css from './GetToKnowButton.module.scss';

type Props = {
    href: string;
    apartment: Apartment
    isDesktopSize: boolean;
};

const GetToKnowButton = ({ href, apartment, isDesktopSize }: Props) => {
    const { t } = useTranslation();

    return (
        <a
            href={href}
            className={`${css.getToKnowButton} hds-button hds-button--${isDesktopSize ? 'supplementary' : 'secondary'
                } hds-button--small`}
        >
            <span className="hds-button__label">
                {t('SEARCH:view')}
                <span className="sr-only">
                    , {t('SEARCH:apartment')} {apartment.apartment_number}
                </span>
            </span>
        </a>

    )
}

export default GetToKnowButton;