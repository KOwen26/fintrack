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
}
