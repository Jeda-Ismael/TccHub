import { Injectable } from '@nestjs/common';
import { ConfigService } from '@nestjs/config';
import { existsSync, mkdirSync, unlinkSync } from 'fs';
import { join } from 'path';

export interface StoredFile {
  url: string;
  path: string;
}

@Injectable()
export class StorageService {
  private uploadsDir: string;

  constructor(private config: ConfigService) {
    this.uploadsDir = join(process.cwd(), this.config.get<string>('UPLOADS_DIR') || 'uploads');
    if (!existsSync(this.uploadsDir)) {
      mkdirSync(this.uploadsDir, { recursive: true });
    }
  }

  // Multer already writes the file to disk (see tcc.controller upload interceptor config).
  // This wraps the resulting filename into a public URL, keeping the interface
  // decoupled so a future S3/MinIO implementation only needs to change this class.
  buildPublicUrl(filename: string): string {
    return `/uploads/${filename}`;
  }

  deleteByUrl(url: string) {
    const filename = url.replace('/uploads/', '');
    const filePath = join(this.uploadsDir, filename);
    if (existsSync(filePath)) {
      unlinkSync(filePath);
    }
  }
}
