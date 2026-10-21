import { Global, Module } from '@nestjs/common';
import { env } from '../config/env.js';
import { SecretBox } from './secret-box.js';

@Global()
@Module({
  providers: [{ provide: SecretBox, useFactory: () => new SecretBox(env().SECRETS_KEY) }],
  exports: [SecretBox],
})
export class SecretBoxModule {}
