import type { ReadingStatus } from './book';

export interface BookSearch {
    readonly text?: string;
    readonly readingStatus?: ReadingStatus;
    readonly owned?: boolean;
    readonly lent?: boolean;
    readonly page?: number;
    readonly perPage?: number;
}
