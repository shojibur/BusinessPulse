<script setup lang="ts">
import { CircleHelp } from '@lucide/vue';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';

defineProps<{ claudeModels: string[] }>();
</script>

<template>
    <Dialog>
        <DialogTrigger as-child>
            <Button variant="outline" size="sm">
                <CircleHelp aria-hidden="true" />
                How AI settings work
            </Button>
        </DialogTrigger>
        <DialogContent class="max-h-[85vh] overflow-y-auto sm:max-w-2xl">
            <DialogHeader>
                <DialogTitle>How AI settings work</DialogTitle>
                <DialogDescription>
                    One set of settings for the whole site. Every company uses
                    them.
                </DialogDescription>
            </DialogHeader>

            <div class="space-y-4 text-sm">
                <section>
                    <h3 class="font-semibold">The three jobs</h3>
                    <ul class="mt-1 list-disc space-y-1 pl-5">
                        <li>
                            <strong>Text</strong> writes the wording of the
                            executive brief and finds decisions and proposed
                            tasks in a meeting transcript. Choose
                            <em>OpenAI-compatible</em> (OpenAI or any service
                            with the same API) or <em>Claude (Anthropic)</em>.
                        </li>
                        <li>
                            <strong>Transcription</strong> turns a meeting
                            recording into text.
                        </li>
                        <li><strong>Voice</strong> reads the brief aloud.</li>
                    </ul>
                    <p class="mt-2 text-muted-foreground">
                        Each job has its own service and key, so you can mix
                        them: for example Claude for text, and OpenAI or Groq
                        for transcription. Transcription and voice work with any
                        service that offers the OpenAI-style audio API. Claude
                        is used for text only.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold">The fields</h3>
                    <dl class="mt-1 space-y-2">
                        <div>
                            <dt class="font-medium">Address (base URL)</dt>
                            <dd class="text-muted-foreground">
                                Where the provider lives. For Claude leave it
                                empty to use the standard address. For an
                                OpenAI-compatible service it usually ends in
                                <code>/v1</code>. It must start with https://
                                and be a public address.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">API key</dt>
                            <dd class="text-muted-foreground">
                                The secret from your provider account. It is
                                stored encrypted, used only by the server, and
                                never shown again. Leave the box empty to keep
                                the saved key, or tick Remove to delete it.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">Model</dt>
                            <dd class="text-muted-foreground">
                                The exact model name from your provider's list.
                                The text model must support structured (JSON
                                schema) output. Claude examples:
                                {{ claudeModels.join(', ') }}.
                            </dd>
                        </div>
                        <div>
                            <dt class="font-medium">Voice</dt>
                            <dd class="text-muted-foreground">
                                The voice name your speech model offers.
                            </dd>
                        </div>
                    </dl>
                </section>

                <section>
                    <h3 class="font-semibold">Where the values come from</h3>
                    <p class="text-muted-foreground">
                        A value saved here wins. An empty value falls back to
                        the server's <code>.env</code> file
                        (<code>AI_BASE_URL</code>, <code>OPENAI_API_KEY</code>,
                        <code>ANTHROPIC_API_KEY</code> and the model names). The
                        status row of each job tells you which one is in use.
                        Changes apply from the next request. Nothing needs a
                        restart.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold">Test connection</h3>
                    <p class="text-muted-foreground">
                        Save first, then press Test. It sends one tiny request
                        with the saved text settings and tells you whether the
                        key, address and model work together. It costs a few
                        tokens. Transcription and voice are not tested here.
                    </p>
                </section>

                <section>
                    <h3 class="font-semibold">Good to know</h3>
                    <ul class="list-disc space-y-1 pl-5 text-muted-foreground">
                        <li>
                            AI never calculates figures, assigns tasks or
                            resolves issues. If a provider is down, the brief
                            falls back to a plain summary and meetings accept a
                            pasted transcript.
                        </li>
                        <li>
                            Keep real client data out until the client has
                            approved sending it to this provider.
                        </li>
                    </ul>
                </section>
            </div>
        </DialogContent>
    </Dialog>
</template>
