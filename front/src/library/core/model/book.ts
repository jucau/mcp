export type ReadingStatus = 'to_read' | 'reading' | 'read' | 'abandoned';

export interface Book {
    readonly id: string;
    readonly title: string;
    readonly author: string;
    readonly isbn: string | null;
    readonly owned: boolean;
    readonly readingStatus: ReadingStatus;
    readonly startedOn: string | null;
    readonly finishedOn: string | null;
    readonly rating: number | null;
    readonly lentTo: string | null;
    readonly lentOn: string | null;
}
