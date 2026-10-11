export interface CatalogEntry {
    readonly isbn: string;
    readonly title: string;
    readonly author: string | null;
    readonly publishedIn: number | null;
    readonly pages: number | null;
}
