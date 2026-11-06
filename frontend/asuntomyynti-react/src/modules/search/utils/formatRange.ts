export const formatRange = (values: string[]) => {
  const [min, max] = values;

  if (min && max) {
    return `${min}-${max} m\u00b2`;
  } else if (min && !max) {
    return `> ${min} m\u00b2`;
  } else if (!min && max) {
    return `< ${max} m\u00b2`;
  }

  return '';
};
