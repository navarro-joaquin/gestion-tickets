export interface AnnouncementTicket {
    code: string;
    label: string;
    number: number;
    station_label: string | null;
}

type AudioContextConstructor = typeof AudioContext;

interface WebkitWindow extends Window {
    webkitAudioContext?: AudioContextConstructor;
}

export class TicketAnnouncer {
    private audioContext: AudioContext | null = null;

    private queue: AnnouncementTicket[] = [];

    private draining = false;

    private enabled = false;

    public get isEnabled(): boolean {
        return this.enabled;
    }

    public get speechSupported(): boolean {
        return typeof window !== 'undefined' && 'speechSynthesis' in window;
    }

    /**
     * Debe invocarse desde un gesto del usuario: los navegadores bloquean el audio
     * y la síntesis de voz hasta que la pantalla recibe una interacción.
     */
    public async enable(): Promise<boolean> {
        const Context = window.AudioContext
            ?? (window as WebkitWindow).webkitAudioContext;

        if (Context) {
            this.audioContext ??= new Context();

            if (this.audioContext.state === 'suspended') {
                await this.audioContext.resume();
            }
        }

        this.enabled = true;

        if (this.speechSupported) {
            window.speechSynthesis.getVoices();
        }

        return this.audioContext === null || this.audioContext.state === 'running';
    }

    public disable(): void {
        this.enabled = false;
        this.queue = [];

        if (this.speechSupported) {
            window.speechSynthesis.cancel();
        }
    }

    public announce(ticket: AnnouncementTicket): void {
        if (!this.enabled) {
            return;
        }

        this.queue.push(ticket);
        void this.drain();
    }

    private async drain(): Promise<void> {
        if (this.draining) {
            return;
        }

        this.draining = true;

        try {
            while (this.queue.length > 0) {
                const ticket = this.queue.shift();

                if (!ticket) {
                    break;
                }

                await this.ring();
                await this.speak(ticket);
            }
        } finally {
            this.draining = false;
        }
    }

    private async ring(): Promise<void> {
        const context = this.audioContext;

        if (context === null) {
            await delay(450);

            return;
        }

        const start = context.currentTime + 0.05;

        this.chime(context, 880, start, 0.28);
        this.chime(context, 660, start + 0.36, 0.32);

        await delay(950);
    }

    private chime(context: AudioContext, frequency: number, start: number, duration: number): void {
        const oscillator = context.createOscillator();
        const gain = context.createGain();

        oscillator.type = 'sine';
        oscillator.frequency.value = frequency;

        gain.gain.setValueAtTime(0.0001, start);
        gain.gain.exponentialRampToValueAtTime(0.35, start + 0.03);
        gain.gain.exponentialRampToValueAtTime(0.0001, start + duration);

        oscillator.connect(gain);
        gain.connect(context.destination);

        oscillator.start(start);
        oscillator.stop(start + duration + 0.02);
    }

    private speak(ticket: AnnouncementTicket): Promise<void> {
        if (!this.speechSupported) {
            return Promise.resolve();
        }

        const destination = ticket.station_label
            ? `Diríjase a ${ticket.station_label}.`
            : 'Diríjase a la ventanilla.';
        const text = `Código ${ticket.code}, número ${ticket.number}. ${destination}`;

        return new Promise((resolve) => {
            const utterance = new SpeechSynthesisUtterance(text);
            const voices = window.speechSynthesis.getVoices();
            const spanishVoice = voices.find((voice) => voice.lang.toLowerCase().startsWith('es'));

            utterance.lang = 'es-BO';
            utterance.rate = 0.92;
            utterance.pitch = 1;
            utterance.volume = 1;

            if (spanishVoice) {
                utterance.voice = spanishVoice;
            }

            let settled = false;

            const finish = (): void => {
                if (settled) {
                    return;
                }

                settled = true;
                resolve();
            };

            utterance.onend = finish;
            utterance.onerror = finish;

            window.speechSynthesis.cancel();
            window.speechSynthesis.speak(utterance);
            window.setTimeout(finish, 12_000);
        });
    }
}

function delay(milliseconds: number): Promise<void> {
    return new Promise((resolve) => {
        window.setTimeout(resolve, milliseconds);
    });
}
