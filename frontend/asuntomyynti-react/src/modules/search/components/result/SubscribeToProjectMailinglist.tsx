import { useState } from 'react';
import { Button, ButtonSize, ButtonVariant, IconEye, IconEyeCrossed, Notification } from 'hds-react';
import { useTranslation } from 'react-i18next';
import axios from 'axios';

import { Project, DataConfig } from '../../../../types/common';
import ErrorToast from '../../../../common/errorToast/ErrorToast';
import useSearchParams from '../../../../hooks/useSearchParams';

import styles from './SubscribeToProjectMailinglist.module.scss';

type Props = {
  project: Project;
  config: DataConfig;
};

enum PostState {
  Initial,
  InProgress,
  Error,
  Success,
}

const SubscribeToProjectMailinglist = ({ project, config }: Props) => {
  const [postState, setPostState] = useState(PostState.Initial);
  const { t } = useTranslation();
  const searchParams = useSearchParams();
  const { housing_company, id } = project;

  const { token, user, static_content } = config;

  const userIsLoggedIn = user.user_id !== 0;
  const userProfilePageURL = static_content.followed_projects_page_url;
  const userHasPreviouslySubscribed = user.followed_projects && user.followed_projects.indexOf(id) !== -1;

  const [isSubscribed, setIsSubscribed] = useState(userHasPreviouslySubscribed || false);

  const handleSubscribe = async () => {
    setPostState(PostState.InProgress);
    try {
      const lang = searchParams.get('lang') || 'fi';
      const result = await axios.post(
        `/${lang}/project/mailinglist`,
        {
          project_id: id,
        },
        {
          headers: {
            'X-CSRF-TOKEN': token,
          },
        }
      );
      if (typeof result.data.follow !== 'undefined') {
        setPostState(PostState.Success);
        setIsSubscribed(result.data.follow);
      } else {
        setPostState(PostState.Error);
      }
    } catch {
      setPostState(PostState.Error);
    }
  };

  const handleUnauthenticatedUser = () => {
    setPostState(PostState.InProgress);
  };

  const renderSubscribeButton = (onClick = () => {}, disabled = false) => {
    const buttonLabel = isSubscribed
      ? t('SEARCH:project-mailinglist-unsubscribe')
      : t('SEARCH:project-mailinglist-subscribe');
    return (
      <Button
        className="subscribe-button"
        variant={ButtonVariant.Supplementary}
        iconStart={isSubscribed ? <IconEyeCrossed aria-hidden="true" /> : <IconEye aria-hidden="true" />}
        size={ButtonSize.Small}
        onClick={onClick}
        disabled={disabled}
        aria-label={`${buttonLabel}, ${housing_company}`}
      >
        {buttonLabel}
      </Button>
    );
  };

  if (!userIsLoggedIn) {
    return (
      <>
        {renderSubscribeButton(handleUnauthenticatedUser, postState === PostState.InProgress)}
        {postState === PostState.InProgress && (
          <Notification
            className={`custom-notification ${styles.subscribeNotification}`}
            label={t('SEARCH:project-mailinglist-should-log-in')}
            position="top-right"
            type="info"
            autoClose
            autoCloseDuration={4000}
            dismissible
            closeButtonLabelText={t('SEARCH:aria-close-notification')}
            onClose={() => setPostState(PostState.Initial)}
          />
        )}
      </>
    );
  }

  return (
    <>
      {postState === PostState.Error && <ErrorToast />}
      {postState === PostState.Success && isSubscribed && (
        <Notification
          className={`custom-notification ${styles.subscribeNotification}`}
          label={t('SEARCH:project-mailinglist-saved')}
          position="top-right"
          type="success"
          autoClose
          autoCloseDuration={4000}
          dismissible
          closeButtonLabelText={t('SEARCH:aria-close-notification')}
          onClose={() => setPostState(PostState.Initial)}
        >
          {housing_company}&nbsp;
          {t('SEARCH:project-mailinglist-success-prefix')}&nbsp;
          {userProfilePageURL ? (
            <a href={userProfilePageURL}>{t('SEARCH:project-mailinglist-success-suffix')}</a>
          ) : (
            t('SEARCH:project-mailinglist-success-suffix')
          )}
        </Notification>
      )}
      {postState === PostState.Success && !isSubscribed && (
        <Notification
          className={`custom-notification ${styles.subscribeNotification}`}
          label={`${housing_company} ${t('SEARCH:project-mailinglist-removed')}`}
          position="top-right"
          type="success"
          autoClose
          autoCloseDuration={4000}
          dismissible
          closeButtonLabelText={t('SEARCH:aria-close-notification')}
          onClose={() => setPostState(PostState.Initial)}
        />
      )}
      {renderSubscribeButton(handleSubscribe, postState === PostState.InProgress)}
    </>
  );
};

export default SubscribeToProjectMailinglist;
