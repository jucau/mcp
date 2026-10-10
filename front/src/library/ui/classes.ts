export const classes = (...names: (string | false | undefined)[]): string => names.filter(Boolean).join(' ');
