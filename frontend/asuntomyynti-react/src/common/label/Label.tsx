import cx from 'classnames';

import css from './Label.module.scss';

type Props = {
  type?: string | undefined;
  children: string | JSX.Element;
};

const Label = ({ type, children }: Props) => {
  const labelClasses = () => {
    return cx(css.label, {
      [css.hitas]: type?.toLowerCase() === 'hitas',
      [css.puolihitas]: type?.toLowerCase() === 'puolihitas',
      [css.haso]: type?.toLowerCase() === 'haso',
    });
  };

  return <span className={labelClasses()}>{children}</span>;
};

export default Label;
