from __future__ import annotations
import discord, logging, asyncio
from discord.ext import commands
from datetime import datetime, UTC, timedelta
from json import load
from typing import TYPE_CHECKING, Any
if __name__ == "__main__":
	from os import path
	from sys import path as sys_path
	sys_path.append(path.abspath(path.join(path.dirname(__file__), '..')))
if TYPE_CHECKING:
	from utils.bot import Bot
from utils.bot import debug_only
import utils.generic as genericUtil
import utils.wt as wtUtil
__reload_deps__ = ("utils.wt", "utils.generic")

altAdminPerm:bool=True
class AdminCog(commands.Cog):
	bot:'Bot'
	def __init__(self, bot:'Bot'):
		self.bot = bot
		self.logger = logging.getLogger(__name__)
		self.logger.setLevel(bot.logLevel)
		self.exclude_file = "userlist_exclude.txt"
		self.logger.debug("Admin Commands initialized")
	async def interaction_check(self, interaction:discord.Interaction):
		if isinstance(interaction.channel, discord.DMChannel):
			self.logger.warning(f"Unauthorized request\n\tCommand:{interaction.command.name}\t\nUser:{interaction.user.name}({interaction.user.id})\n\tReason: Admin command in DMs")
			await interaction.response.send_message("You need to execute this command in a server (Required to check for permissions)")
			return False
		elif (
			(interaction.user.guild_permissions.administrator and interaction.guild_id == 917850361019125820) or # PECK admin 
			interaction.user.id in [
				332030423913725953, # Maho Yoshino
				171971803625816075, # Tzatziki
				311555617032765451, # eevee
			]
			or (altAdminPerm and self.bot.debug and interaction.user.id == 709449854371364895) # Roxy (alt)
		): 
			return True
		self.logger.warning(f"Unauthorized request\n\tCommand:{interaction.command.name}\t\nUser:{interaction.user.name}({interaction.user.id})\n\tReason: No permission")
		await interaction.response.send_message("You don't have the required Permissions", ephemeral=True, 	delete_after=5)
		return False

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	@discord.app_commands.describe(count="The amount of messages to purge")
	async def purge(self, interaction:discord.Interaction, count:int):
		if count < 1:
			await interaction.response.send_message(content="Count too low.", ephemeral=True)
			return
		await interaction.response.send_message(f"Purging {count} messages", ephemeral=True)
		await interaction.channel.purge(limit=count)

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	@discord.app_commands.describe(userid="The user's id to send the DM to", content="What will be sent")
	async def senddm(self, interaction: discord.Interaction, userid:str, content:str):
		"""Sends a DM to a specified user, used with an ID, Only usable by the bot owner"""
		userid = int(userid)
		user = await self.bot.fetch_user(userid)
		await user.send(content=f"{content}\n-# From the PECK administration")
		await interaction.response.send_message(f"{user} has recieved your message")

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def say(self, interaction:discord.Interaction, content:str):
		"""Says the content as the bot, Only usable as the owner of the bot"""
		await interaction.response.defer(ephemeral=True)
		await interaction.channel.send(content.replace("\\n", "\n"))
		await interaction.delete_original_response()

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def top_members(self, interaction: discord.Interaction, number:int=8):
		"""Requests the top `number` highest SQB Activity people from the squadron"""
		await interaction.response.defer(thinking=True)
		await self.bot.squadron.updateMembers()
		topmembers = self.bot.squadron.SortBySQBPoints()
		if len(topmembers) == 0:
			await interaction.edit_original_response(content=f"There seems to be nobody in the squadron with any SQB points.")
			return
		if len(topmembers) > number: topmembers = topmembers[:number]
		top_10_message = f"Top {len(topmembers)} Users:\n"
		for i, user in enumerate(topmembers, 1):
			top_10_message += f"{i}. {genericUtil.demarkdownify(user.name)}: {user.sqb_rating}\n"
		await interaction.edit_original_response(content=top_10_message)

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	@discord.app_commands.describe(username="Enter War Thunder username, who the message gets sent to",dc_user="Ping a user to send a message to")
	async def sqbrecruit(self, interaction: discord.Interaction, username:str|None=None, dc_user:discord.Member|None=None):
		'''Recruits a given person for SQB, Only usable by admins'''
		await interaction.response.defer(thinking=True)
		identifier = None
		if username is not None:	identifier = username
		elif dc_user is not None:	identifier = dc_user.id
		else: return await interaction.edit_original_response(content="You must give me any user identifier.")
		if isinstance(identifier, int):
			user = self.bot.db.getByDID(identifier)
			user = user[0] if user else None
		elif isinstance(identifier, str):
			user = self.bot.db.getByName(identifier)
		else:
			raise ValueError(f"Invalid type given for identifier '{type(identifier)}'")
		if user is None:
			self.logger.debug(f"Could not find user '{identifier}' in the database")
			await interaction.edit_original_response(content=f"Could not find user '{identifier}' in the database")
			return
		current_br = wtUtil.sqb_br(True)
		message = f"Hello {user.username}!\nYou have been drafted for SQB.\nThe current BR is {current_br[0]}\n{await genericUtil.random_propaganda(self.bot)}"
		dc_user2 = self.bot.get_user(user.discord_id)
		async def accept(interaction2:discord.Interaction):
			await interaction2.followup.send(f"Splendid, please join <#{self.bot.sqbChID}> to start participating!")
			await interaction.edit_original_response(content=f"User '{interaction2.user.name}' has accepted participating.")
			return True
		async def deny(interaction2:discord.Interaction):
			await interaction2.followup.send("Understood.")
			await interaction.edit_original_response(content=f"User '{interaction2.user.name}' has denied participating this time.")
			return True
		try:
			await dc_user2.send(message, view=genericUtil.genericButtons(acceptFunc=accept, denyFunc=deny, acceptLabel="Accept Invitation", denyLabel="Deny Invitation", removeButtonsAfter=True))
			await interaction.edit_original_response(content=f"Message sent to {dc_user2.name} (War Thunder username: {user.username})")
		except discord.Forbidden:
			await interaction.edit_original_response(content=f"Could not send DM to {dc_user2.name} (War Thunder username: {user.username}).")

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def sqb_brackets(self, interaction:discord.Interaction):
		"""Posts the seasonal schedule for SQB"""
		await interaction.response.defer(thinking=True)
		for i in (await interaction.channel.pins()):
			if i.content.startswith("1st week:") and i.author.id == self.bot.user.id:
				await i.delete()
				self.logger.debug("Pinned message found and promptly deleted")
				break
		await interaction.edit_original_response(content=wtUtil.sqb_br())
		await (await interaction.original_response()).pin(reason="Pinning SQB BR message")
		await interaction.channel.purge(limit=1, reason="Removing pin message")

	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def get_user(self, interaction:discord.Interaction, username:str):
		class InvalidUsersLView(discord.ui.LayoutView):
			def __init__(self, bot:'Bot'):
				super().__init__(timeout=0)
				self.bot = bot
				self.user = bot.db.getByName(username)
				if self.user is None:
					self.add_item(discord.ui.TextDisplay("No such user is in the database"))
				else:
					self.add_item(discord.ui.TextDisplay(f"### Username\n\t'{genericUtil.demarkdownify(self.user.username)}'"))
					self.add_item(discord.ui.TextDisplay(f"### ID\n\t{self.user.discord_id}"))
					self.add_item(discord.ui.TextDisplay(f"### Join date\n\t{self.user.joindate.strftime("%Y-%m-%d")}"))
					self.add_item(discord.ui.TextDisplay(f"### Status\n\t{self.user.status}"))
					if self.user.timezone is not None:
						tmp = (datetime.now(UTC)+timedelta(hours=self.user.timezone))
						self.add_item(discord.ui.TextDisplay(f"### Timezone\n\tUTC {"-" if self.user.timezone<0 else "+"}{self.user.timezone} (Currently it is `{tmp.strftime("%H:%M")}` (`{(tmp+timedelta(hours=1)).strftime("%H:%M")}` if DST is in effect) for them.)"))
					else:
						self.add_item(discord.ui.TextDisplay(f"### Timezone\n\tUnknown"))
		await interaction.response.send_message(view=InvalidUsersLView(self.bot))
	
	@discord.app_commands.command()
	@discord.app_commands.guild_only()
	async def verify_user(self, interaction:discord.Interaction, username:str, user:discord.Member):
		await interaction.response.defer(thinking=True)
		await self.bot.squadron.updateMembers()
		dbuser = self.bot.db.getByName(username)
		member = self.bot.squadron.getMember(username)
		if dbuser is None and member is None:
			await interaction.edit_original_response(content="User doesn't yet exist in database, nor on the War Thunder page")
			return
		elif dbuser is not None and dbuser.status != self.bot.db.Status.MEMBER:
			dbuser.status = self.bot.db.Status.MEMBER
			dbuser.discord_id = user.id
			dbuser.push()
			await interaction.edit_original_response(content=f"Successfully verified user '{username}'")
		elif dbuser is None and member is not None:
			_ = wtUtil.get_user_ids(username)[username]
			if _ is not None:
				self.bot.db.add_user(_, username=username, discord_id=user.id, joindate=member.joindate.date(), status=self.bot.db.Status.MEMBER)
				await interaction.edit_original_response(content=f"Successfully verified user '{username}'")

async def setup(bot: 'Bot'):
	await bot.add_cog(AdminCog(bot))
