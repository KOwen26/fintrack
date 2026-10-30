export default class StringHelper {
    /**
     * Generate a random string of a given length.
     *
     * @param {number} length - The length of the random string
     * @returns {string}
     */
    public static random(length: number = 10): string {
        const characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
        let result = '';
        const characters_length = characters.length;

        for (let i = 0; i < length; i++) {
            result += characters.charAt(Math.floor(Math.random() * characters_length));
        }

        return result;
    }

    public static getInitials(name?: string) {
        if (!name) return '';

        return (name ?? '')
            .replace(/[^a-zA-Z]/g, '')
            .slice(0, 2)
            .toUpperCase();
    }

    /**
     * Lowercase, collapse every non letter/number run to a single
     * underscore, and trim underscores from both ends.
     */
    public static toSnakeCase(value: string): string {
        return value
            .toLowerCase()
            .replace(/[^\p{L}\p{N}]+/gu, '_')
            .replace(/^_+|_+$/g, '');
    }

    /**
     * Convert a snake_case string to Title Case words — "bills_rent" → "Bills Rent".
     */
    public static snakeToTitle(value: string): string {
        return value
            .split('_')
            .map((word) => (word ? word.charAt(0).toUpperCase() + word.slice(1) : word))
            .join(' ');
    }
}
