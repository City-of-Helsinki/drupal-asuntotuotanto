export const fullURL = (path: string) => {
  if (!path) {
    return '#';
  }
  if (path.toLowerCase().startsWith('http')) {
    return path;
  }
  return `//${path}`;
};
