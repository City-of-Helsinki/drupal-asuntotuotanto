export const formattedLivingArea = (value: number) => {
  return `${value.toLocaleString('fi-FI')} m\u00b2`;
};

export default formattedLivingArea;
