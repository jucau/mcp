export interface Authorization {
    isSignedIn(): boolean;
    signIn(): Promise<void>;
    completeSignIn(callback: URL): Promise<void>;
    accessToken(): Promise<string | undefined>;
}
