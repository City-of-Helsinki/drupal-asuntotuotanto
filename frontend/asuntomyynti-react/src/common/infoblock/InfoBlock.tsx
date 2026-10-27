import { useEffect, useRef, useState } from 'react';
import { IconAngleRight, IconCross } from 'hds-react';
import { useTranslation } from 'react-i18next';
import { DataConfig } from '../../types/common';
import useSessionStorageState from '../../hooks/useSessionStorageState';

import css from './InfoBlock.module.scss';

const BREAK_POINT = 768;

type Props = {
  config: DataConfig | undefined;
  type: string;
};

const InfoBlock = ({ config, type }: Props) => {
  const [width, setWidth] = useState(window.innerWidth);
  const [isHidden, setHidden] = useSessionStorageState({ defaultValue: false, key: `infoBlockHidden-${type}` });
  const [isMinified, setIsMinified] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const infoBlockRef = useRef<HTMLDivElement>(null);
  const { t } = useTranslation();
  const isMobileSize = width <= BREAK_POINT;

  const { static_content } = config || {};
  const {
    haso_instruction_text,
    haso_instruction_text_mobile,
    haso_instruction_icon_text,
    haso_instruction_url,
    hitas_instruction_text,
    hitas_instruction_text_mobile,
    hitas_instruction_icon_text,
    hitas_instruction_url,
  } = static_content || {};

  let icon_text = hitas_instruction_icon_text;
  let instruction_text = hitas_instruction_text;
  let instruction_text_mobile = hitas_instruction_text_mobile;
  let url = hitas_instruction_url;

  if (type.toLowerCase() === 'haso') {
    icon_text = haso_instruction_icon_text;
    instruction_text = haso_instruction_text;
    instruction_text_mobile = haso_instruction_text_mobile;
    url = haso_instruction_url;
  }

  const handleResize = () => {
    setWidth(window.innerWidth);
  };

  useEffect(() => {
    window.addEventListener('resize', handleResize);
    return () => window.removeEventListener('resize', handleResize);
  }, []);

  useEffect(() => {
    window.onscroll = () => {
      if (scrollRef.current) {
        if (window.pageYOffset > scrollRef.current.offsetTop + scrollRef.current.offsetHeight) {
          setIsMinified(true);
        } else {
          setIsMinified(false);
        }
      }
    };
  }, []);

  const hideInfoBlock = () => {
    setHidden(true);

    if (infoBlockRef.current) {
      infoBlockRef.current.focus();
    }
  };

  const renderIcon = () => (
    <svg className={css.blockIcon} viewBox="0 0 24 24" xmlns="//www.w3.org/2000/svg" aria-hidden="true">
      <path
        fill="currentColor"
        d="M16,2 L16,6 L22,6 L22,21 L16,21 C14,21 12.6666667,21.6666667 12,23 C11.3333333,
      21.6666667 10,21 8,21 L2,21 L2,6 L8.07071017,6.0006281 C8.44631048,3.46044118 10.2844113,2.08264672 12.7715358,
      2.00360409 L13,2 L16,2 Z M11.0004944,16.2672785 C10.4024204,16.6130175 10,17.2595136 10,18 C10,
      18.4740843 10.1642263,18.9202352 10.4605988,19.2769327 C10.4829576,19.3038424 10.5036662,19.3315776 10.5227361,
      19.3600153 C10.6861188,19.4111314 10.8459852,19.4689212 11.0014579,19.5324153 Z M20,8 L16,8 L16,16 L13,
      16 L13.0001847,19.5317448 C13.7959601,19.2070249 14.7067868,19.0316882 15.7132352,19.0039203 L16,19 L20,
      19 L20,8 Z M8,8 L4,8 L4,19 L8,19 L8.12700938,19.0028632 C8.04342457,18.6792423 8,18.3427848 8,18 L8,18 L8,
      8 Z M14,8 L10,8 L9.99987956,14.5351985 C10.5341956,14.2260885 11.1466497,14.0370268 11.8003597,14.0048953 L12,
      14 L14,14 L14,8 Z M14,4 L13,4 C11.4519646,4 10.4223909,4.62797088 10.1044641,6.00033085 L14,6 L14,4 Z"
      ></path>
    </svg>
  );

  const renderUrl = () => (
    <a href={url}>
      <span>{t('SEARCH:read-more')}</span> <IconAngleRight aria-hidden="true" />
    </a>
  );

  const renderFullContent = () => (
    <>
      {renderIcon()}
      <div className={css.message}>
        {instruction_text} {renderUrl()}
      </div>
    </>
  );

  const renderMinifiedContent = () => (
    <>
      {renderIcon()}
      <div>{icon_text}</div>
    </>
  );

  const renderMobileContent = () => (
    <>
      {renderIcon()}
      <div className={css.message}>
        {instruction_text_mobile} {renderUrl()}
      </div>
    </>
  );

  if (!static_content) {
    return null;
  }

  if (isMobileSize) {
    return (
      <div className={css.container} role="complementary">
        <div className={css.wrapper}>
          <div className={css.content}>{renderMobileContent()}</div>
        </div>
      </div>
    );
  }

  if (isHidden) {
    return <div ref={infoBlockRef} tabIndex={-1} />;
  }

  return (
    <div role="complementary">
      {isMinified && (
        <div>
          <a href={url} className={css.minifiedContainer}>
            {renderMinifiedContent()}
          </a>
        </div>
      )}
      <div className={css.container} ref={scrollRef}>
        <div className={css.wrapper}>
          <div className={css.content}>{renderFullContent()}</div>
          <button
            className={css.closeIcon}
            onClick={() => hideInfoBlock()}
            aria-label={t('SEARCH:aria-hide-info-block')}
          >
            <IconCross aria-hidden="true" />
          </button>
        </div>
      </div>
    </div>
  );
};

export default InfoBlock;
