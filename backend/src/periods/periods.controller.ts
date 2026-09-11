import { Body, Controller, Delete, Get, Param, Post, Put, UseGuards } from '@nestjs/common';
import { Role } from '@prisma/client';
import { JwtAuthGuard } from '../auth/guards/jwt-auth.guard';
import { RolesGuard } from '../common/guards/roles.guard';
import { Roles } from '../common/decorators/roles.decorator';
import { PeriodsService } from './periods.service';
import { PeriodDto } from './dto/period.dto';

@Controller('periods')
@UseGuards(JwtAuthGuard, RolesGuard)
export class PeriodsController {
  constructor(private service: PeriodsService) {}

  @Get()
  findAll() {
    return this.service.findAll();
  }

  @Post()
  @Roles(Role.COORDINATION)
  create(@Body() dto: PeriodDto) {
    return this.service.create(dto);
  }

  @Put(':id')
  @Roles(Role.COORDINATION)
  update(@Param('id') id: string, @Body() dto: PeriodDto) {
    return this.service.update(id, dto);
  }

  @Delete(':id')
  @Roles(Role.COORDINATION)
  remove(@Param('id') id: string) {
    return this.service.remove(id);
  }
}
